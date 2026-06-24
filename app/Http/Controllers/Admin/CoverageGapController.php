<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunGapScoutJob;
use App\Models\Admin;
use App\Models\Course;
use App\Models\CourseFreshnessConfig;
use App\Models\CourseSource;
use App\Models\CourseTopic;
use App\Models\CoverageGap;
use App\Models\GapDraft;
use App\Models\GapInsertion;
use App\Models\GapScoutRun;
use App\Models\Module;
use App\Models\TrustedSource;
use App\Services\GapInserter;
use App\Services\GapPlacer;
use App\Services\TopicSuggester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * P26 Fase A — UI dello Scout di copertura (gated P26_ENABLED). Lancia l'analisi async per corso
 * e gestisce i gap candidati (accetta/scarta, HITL). Solo rilevamento: niente Compose/Insert.
 */
class CoverageGapController extends Controller
{
    public function __construct()
    {
        abort_unless(config('services.p26.enabled'), 404);
    }

    public function index()
    {
        $courses = Course::active()->with('freshnessConfig')
            ->withCount(['coverageGaps as suggested_gaps_count' => fn ($q) => $q->where('status', 'suggested')])
            ->orderBy('name')->get();

        return view('admin.coverage.index', compact('courses'));
    }

    public function show(Request $request, Course $course)
    {
        // P26.2 — topic effettivi (pivot, o legacy singolo) con stato fonti per ciascuno.
        $courseTopics = $course->effectiveTopics()->map(fn ($t) => [
            'topic' => $t['topic'], 'weight' => $t['weight'],
            'has_sources' => TrustedSource::topic($t['topic'])->approved()->exists(),
        ])->values();
        $topicsWithoutSources = $courseTopics->where('has_sources', false)->pluck('topic')->values();
        $hasAnyApprovedSources = $courseTopics->where('has_sources', true)->isNotEmpty();

        // Gap suggeriti: filtro per topic + ordine (PRIMARY prima, poi confidenza).
        $filterGapTopic = $request->query('gap_topic') ?: null;
        $q = CoverageGap::forCourse($course->id)->where('status', 'suggested');
        if ($filterGapTopic) {
            $q->where('source_topic', $filterGapTopic);
        }
        $gaps = $q->orderByRaw("CASE WHEN source_weight = 'primary' THEN 0 ELSE 1 END")
            ->orderByDesc('confidence')->orderByDesc('created_at')->get();
        $gapTopics = CoverageGap::forCourse($course->id)->where('status', 'suggested')
            ->whereNotNull('source_topic')->distinct()->orderBy('source_topic')->pluck('source_topic');

        $accepted = CoverageGap::forCourse($course->id)->where('status', 'accepted')
            ->with('draft')->orderByDesc('updated_at')->get();
        $lastRun = GapScoutRun::where('course_id', $course->id)->orderByDesc('created_at')->first();
        $sourceTopics = TrustedSource::query()->distinct()->orderBy('topic')->pluck('topic');
        $suggestion = session('topics_suggestion'); // proposta multi (flash)

        return view('admin.coverage.show', compact('course', 'courseTopics', 'topicsWithoutSources',
            'hasAnyApprovedSources', 'gaps', 'gapTopics', 'filterGapTopic', 'accepted', 'lastRun', 'sourceTopics', 'suggestion'));
    }

    /**
     * Imposta il topic del corso (HITL). P26.1: lo slug è normalizzato e può essere NUOVO; l'anti-drift
     * è affidato al TopicSuggester (propone il riuso di un esistente) + alla lista visibile, non a un
     * whitelist rigido. Resta possibile sceglierlo/digitarlo a mano.
     */
    public function setTopic(Request $request, Course $course)
    {
        $data = $request->validate(['topic' => 'required|string|max:120']);
        $topic = Str::slug($data['topic']);
        if ($topic === '') {
            return back()->with('error', 'Topic non valido.');
        }

        CourseFreshnessConfig::updateOrCreate(['course_id' => $course->id], ['topic' => $topic]);

        return back()->with('success', "Topic del corso impostato a «{$topic}».");
    }

    /** P26.1 — L'agente legge il corso e PROPONE un topic (riuso di un esistente se affine). Isolato. */
    public function suggestTopic(Course $course)
    {
        try {
            $suggestion = app(TopicSuggester::class)->suggest($course);
        } catch (\Throwable $e) {
            return back()->with('error', 'Suggerimento topic non riuscito: ' . $e->getMessage() . ' Puoi impostarlo a mano.');
        }

        // Flash per precompilare il campo (l'admin conferma con "Salva topic": niente è automatico).
        return back()->with('topic_suggestion', $suggestion);
    }

    public function analyze(Course $course)
    {
        $effective = $course->effectiveTopics();
        if ($effective->isEmpty()) {
            return back()->with('error', 'Imposta prima i topic del corso.');
        }
        $hasAny = $effective->contains(fn ($t) => TrustedSource::topic($t['topic'])->approved()->exists());
        if (!$hasAny) {
            return back()->with('error', 'Nessuna fonte attendibile approvata per i topic del corso. Aggiungi/approva fonti prima di analizzare.');
        }

        RunGapScoutJob::dispatch($course->id);

        return back()->with('success', "Analisi di copertura avviata per «{$course->name}». Cerca nelle fonti approvate di tutti i topic: ricarica tra poco per vedere i gap candidati.");
    }

    /** P26.2 — Imposta i topic del corso (multi, pesati) sostituendo la pivot. HITL. */
    public function setTopics(Request $request, Course $course)
    {
        $data = $request->validate([
            'topics' => 'required|array|min:1',
            'topics.*' => 'nullable|string|max:120',
            'weights' => 'nullable|array',
            'weights.*' => 'nullable|in:primary,secondary',
        ]);

        $rows = [];
        foreach ($data['topics'] as $i => $raw) {
            $slug = Str::slug((string) $raw);
            if ($slug === '' || isset($rows[$slug])) {
                continue;
            }
            $rows[$slug] = (($data['weights'][$i] ?? 'secondary') === 'primary') ? 'primary' : 'secondary';
        }
        if ($rows === []) {
            return back()->with('error', 'Indica almeno un topic valido.');
        }
        // Esattamente UN primary.
        $primaries = array_keys($rows, 'primary', true);
        if ($primaries === []) {
            $rows[array_key_first($rows)] = 'primary';
        } elseif (count($primaries) > 1) {
            foreach (array_slice($primaries, 1) as $k) {
                $rows[$k] = 'secondary';
            }
        }

        DB::transaction(function () use ($course, $rows) {
            CourseTopic::where('course_id', $course->id)->delete();
            foreach ($rows as $slug => $weight) {
                CourseTopic::create(['course_id' => $course->id, 'topic' => $slug, 'weight' => $weight]);
            }
        });
        // Retrocompat: tieni il vecchio singolo allineato al primary.
        $primary = array_search('primary', $rows, true);
        CourseFreshnessConfig::updateOrCreate(['course_id' => $course->id], ['topic' => $primary]);

        return back()->with('success', 'Topic del corso aggiornati.');
    }

    /** P26.2 — Propone la LISTA pesata di topic; l'admin conferma/edita (niente automatico). Isolato. */
    public function suggestTopicsAction(Course $course)
    {
        try {
            $suggestion = app(TopicSuggester::class)->suggestTopics($course);
        } catch (\Throwable $e) {
            return back()->with('error', 'Suggerimento topic non riuscito: ' . $e->getMessage() . ' Puoi impostarli a mano.');
        }

        return back()->with('topics_suggestion', $suggestion);
    }

    public function accept(CoverageGap $gap)
    {
        $gap->update(['status' => 'accepted', 'reviewed_by' => $this->adminId(), 'reviewed_at' => now()]);

        return back()->with('success', "Gap «{$gap->title}» accettato (entrerà nelle fasi di stesura).");
    }

    public function dismiss(CoverageGap $gap)
    {
        $gap->update(['status' => 'dismissed', 'reviewed_by' => $this->adminId(), 'reviewed_at' => now()]);

        return back()->with('success', 'Gap scartato.');
    }

    // ===================== Fase B — Compose bozze =====================

    /** Genera (o rigenera) la bozza per un gap ACCETTATO. Async. NON inserisce nulla. */
    public function generate(CoverageGap $gap)
    {
        abort_unless($gap->status === 'accepted', 422, 'La bozza si genera solo su un gap accettato.');

        \App\Jobs\RunGapComposeJob::dispatch($gap->id);

        return back()->with('success', "Generazione bozza avviata per «{$gap->title}» (formatore + studente). Ricarica tra poco.");
    }

    public function draftView(CoverageGap $gap)
    {
        abort_unless($gap->status === 'accepted', 404);
        $draft = $gap->draft;
        $course = $gap->course;

        // Per la UI di Posizione (Fase C): heading formatore + moduli studente.
        $source = CourseSource::where('course_id', $course->id)->orderByDesc('created_at')->orderByDesc('id')->first();
        $headings = collect($source?->blocks ?? [])
            ->filter(fn ($b) => in_array(($b['type'] ?? ''), ['PART', 'H1', 'H2'], true) && trim((string) ($b['text'] ?? '')) !== '')
            ->map(fn ($b) => ['id' => $b['id'], 'text' => $b['text']])->values();
        $modules = $course->modules()->get(['id', 'title']);
        $insertion = $draft ? GapInsertion::where('gap_draft_id', $draft->id)->where('status', 'inserted')->latest()->first() : null;
        $isMinor = optional($course->freshnessConfig)->audience === 'minor';

        return view('admin.coverage.draft', compact('gap', 'draft', 'headings', 'modules', 'insertion', 'isMinor'));
    }

    /** Salva le modifiche dell'admin al testo della bozza (resta editabile prima dell'approvazione). */
    public function updateDraft(Request $request, GapDraft $draft)
    {
        $data = $request->validate([
            'formatore_html' => 'nullable|string',
            'studente_html' => 'nullable|string',
        ]);
        $draft->update([
            'formatore_html' => $data['formatore_html'] ?? $draft->formatore_html,
            'studente_html' => $data['studente_html'] ?? $draft->studente_html,
        ]);

        return back()->with('success', 'Bozza salvata.');
    }

    /** Approva la bozza: pronta per la Fase D (inserimento). NON inserisce nulla ora. */
    public function approveDraft(GapDraft $draft)
    {
        $draft->update(['status' => 'approved', 'reviewed_by' => $this->adminId(), 'reviewed_at' => now()]);

        return back()->with('success', 'Bozza approvata: pronta per l\'inserimento (Fase D). Nessuna modifica al corso è stata fatta.');
    }

    public function discardDraft(GapDraft $draft)
    {
        $draft->update(['status' => 'discarded', 'reviewed_by' => $this->adminId(), 'reviewed_at' => now()]);

        return back()->with('success', 'Bozza scartata.');
    }

    // ===================== Fase C — Place (posizione) =====================

    /** L'agente PROPONE una posizione (formatore + studente). L'admin poi conferma/sposta. Isolato. */
    public function proposePlace(CoverageGap $gap)
    {
        $draft = $gap->draft;
        abort_unless($draft, 404);

        try {
            $p = app(GapPlacer::class)->propose($draft);
        } catch (\Throwable $e) {
            return back()->with('error', 'Proposta posizione non riuscita: ' . $e->getMessage());
        }

        $moduleId = Module::where('course_id', $gap->course_id)->whereKey($p['student_module_id'])->value('id');
        $draft->update([
            'place_formatore_block_id' => $p['formatore_after_block_id'],
            'place_student_module_id' => $moduleId,
            'place_student_anchor' => $p['student_anchor'],
            'placement_confirmed' => false, // è una proposta: va confermata a mano
        ]);

        return back()->with('success', 'Posizione proposta — rivedi e conferma. ' . $p['reason']);
    }

    /** L'admin CONFERMA (o corregge) la posizione: solo da qui l'inserimento è abilitato. */
    public function confirmPlace(Request $request, CoverageGap $gap)
    {
        $draft = $gap->draft;
        abort_unless($draft, 404);

        $data = $request->validate([
            'place_formatore_block_id' => 'required|string',
            'place_student_module_id' => 'nullable|string',
            'place_student_anchor' => 'nullable|string',
        ]);

        // Lo studente è opzionale; se indicato, il modulo dev'essere del corso e l'ancora presente.
        $moduleId = null;
        if (!empty($data['place_student_module_id'])) {
            $module = Module::where('course_id', $gap->course_id)->find($data['place_student_module_id']);
            if (!$module) {
                return back()->with('error', 'Modulo studente non valido per questo corso.');
            }
            $anchor = trim((string) ($data['place_student_anchor'] ?? ''));
            if ($anchor === '' || mb_strpos(strip_tags((string) $module->content), $anchor) === false) {
                return back()->with('error', 'Ancora studente assente nel modulo scelto: copia una frase esatta dal modulo.');
            }
            $moduleId = $module->id;
        }

        $draft->update([
            'place_formatore_block_id' => $data['place_formatore_block_id'],
            'place_student_module_id' => $moduleId,
            'place_student_anchor' => $moduleId ? $data['place_student_anchor'] : null,
            'placement_confirmed' => true,
        ]);

        return back()->with('success', 'Posizione confermata: ora puoi inserire.');
    }

    // ===================== Fase D — Insert / Revert =====================

    public function insert(Request $request, CoverageGap $gap)
    {
        $draft = $gap->draft;
        abort_unless($draft, 404);

        try {
            app(GapInserter::class)->insert($draft, $request->boolean('minor_confirmed'));
        } catch (\Throwable $e) {
            if ($e->getMessage() === 'minor_confirmation_required') {
                return back()->with('error', '⚠ Corso per MINORI: serve la conferma esplicita per inserire. Nessuna modifica fatta.');
            }
            return back()->with('error', 'Inserimento non riuscito: ' . $e->getMessage());
        }

        return back()->with('success', 'Sezione inserita nel corso (formatore + studente). È reversibile: usa «Annulla inserimento».');
    }

    public function revert(GapInsertion $insertion)
    {
        app(GapInserter::class)->revert($insertion);

        return back()->with('success', 'Inserimento annullato: il corso è tornato esattamente allo stato precedente.');
    }

    private function adminId(): ?string
    {
        return Admin::where('email', session('admin_email'))->value('id');
    }
}
