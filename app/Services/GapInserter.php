<?php

namespace App\Services;

use App\Models\CourseChangelog;
use App\Models\CourseSource;
use App\Models\GapDraft;
use App\Models\GapInsertion;
use App\Models\InstructorManualSection;
use App\Models\Module;
use App\Models\StudentSourceVersion;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * P26 Fase D — Inserimento coordinato di una bozza approvata, APPEND-ONLY e REVERSIBILE.
 *
 * insert():
 *  - FORMATORE course_sources: nuova versione (bump MINORE) coi blocchi nuovi inseriti DOPO il
 *    block_id scelto; id nuovi fuori schema ("-insN") + meta.origin='gap_insert' → niente collisione,
 *    i block_id esistenti NON cambiano (le proposte aperte non si orfanano).
 *  - FORMATORE live (instructor_manual_sections): best-effort, append nella sezione affine, con
 *    snapshot del content_html PRE-inserimento per il revert.
 *  - STUDENTE modules.content: splice HTML dopo l'ancora; snapshot completo su student_source_versions.
 *  - changelog kind='insert'; registro gap_insertions con tutto per l'undo.
 *
 * revert(): ripristina TUTTO allo stato precedente (append-only: nuove versioni = copia della
 * precedente; sezione live ripristinata dallo snapshot). Mai delete di versioni/blocchi esistenti.
 */
class GapInserter
{
    public function insert(GapDraft $draft, bool $minorConfirmed = false): GapInsertion
    {
        return DB::transaction(function () use ($draft, $minorConfirmed) {
            if ($draft->status !== 'approved') {
                throw new RuntimeException('La bozza non è approvata.');
            }
            if (!$draft->placement_confirmed || !$draft->place_formatore_block_id) {
                throw new RuntimeException('Posizione non confermata.');
            }

            $gap = $draft->gap;
            $course = $gap->course;

            // Gate minori: su corso audience=minor serve conferma esplicita (HITL aggiuntivo).
            $audience = optional($course->freshnessConfig)->audience ?? 'adult';
            if ($audience === 'minor' && !$minorConfirmed) {
                throw new RuntimeException('minor_confirmation_required');
            }

            // ---------- FORMATORE: course_sources (nuova versione, blocchi inseriti) ----------
            $current = CourseSource::where('course_id', $course->id)
                ->orderByDesc('created_at')->orderByDesc('id')->first();
            if (!$current) {
                throw new RuntimeException('Nessun course_sources per il corso.');
            }
            $blocks = $current->blocks ?? [];
            $anchorId = (string) $draft->place_formatore_block_id;
            $idx = $this->indexOfBlock($blocks, $anchorId);
            if ($idx === null) {
                throw new RuntimeException("Block_id ancora non trovato nel sorgente: {$anchorId}");
            }

            $newBlocks = GapHtml::toBlocks((string) $draft->formatore_html, $anchorId);
            if ($newBlocks === []) {
                throw new RuntimeException('La bozza formatore non produce blocchi.');
            }
            $augmented = array_values(array_merge(
                array_slice($blocks, 0, $idx + 1),
                $newBlocks,
                array_slice($blocks, $idx + 1)
            ));

            $fromVer = $current->version;
            $toVer = $this->nextMinor($fromVer);
            CourseSource::create(['course_id' => $course->id, 'version' => $toVer, 'blocks' => $augmented]);

            // ---------- FORMATORE live: append nella sezione affine (snapshot per revert) ----------
            $sectionId = null;
            $sectionBefore = null;
            $anchorText = $this->blockText($blocks[$idx]);
            $section = $this->sectionFor($course->id, $anchorText);
            if ($section) {
                $sectionId = $section->id;
                $sectionBefore = $section->content_html;
                $section->update(['content_html' => (string) $section->content_html . "\n" . (string) $draft->formatore_html]);
            }

            // ---------- STUDENTE: splice HTML + versione ----------
            $studentModuleId = null;
            $studentFrom = null;
            $studentTo = null;
            if ($draft->place_student_module_id && trim((string) $draft->place_student_anchor) !== '') {
                $module = Module::where('course_id', $course->id)->find($draft->place_student_module_id);
                if ($module) {
                    $spliced = GapHtml::spliceAfter((string) $module->content, (string) $draft->place_student_anchor, (string) $draft->studente_html);
                    if ($spliced === null) {
                        throw new RuntimeException('Ancora studente non trovata: ' . mb_substr((string) $draft->place_student_anchor, 0, 60));
                    }
                    // Baseline PRE-inserimento (per il revert), poi aggiorna, poi nuova versione.
                    $studentFrom = $this->ensureStudentBaseline($course->id);
                    $module->update(['content' => $spliced]);
                    $studentTo = $this->nextMinor($studentFrom);
                    StudentSourceVersion::create(['course_id' => $course->id, 'version' => $studentTo, 'content' => $this->studentSnapshot($course->id)]);
                    $studentModuleId = $module->id;
                }
            }

            // ---------- changelog + registro inserimento ----------
            CourseChangelog::create(['course_id' => $course->id, 'content_source' => 'instructor',
                'version_from' => $fromVer, 'version_to' => $toVer, 'kind' => 'insert',
                'summary' => 'Inserimento sezione (gap): ' . mb_substr((string) $gap->title, 0, 100)]);
            if ($studentTo) {
                CourseChangelog::create(['course_id' => $course->id, 'content_source' => 'student',
                    'version_from' => $studentFrom, 'version_to' => $studentTo, 'kind' => 'insert',
                    'summary' => 'Inserimento studente (gap): ' . mb_substr((string) $gap->title, 0, 100)]);
            }

            return GapInsertion::create([
                'gap_draft_id' => $draft->id, 'course_id' => $course->id,
                'formatore_version_from' => $fromVer, 'formatore_version_to' => $toVer,
                'inserted_block_ids' => array_column($newBlocks, 'id'),
                'instructor_section_id' => $sectionId, 'instructor_section_html_before' => $sectionBefore,
                'student_module_id' => $studentModuleId, 'student_version_from' => $studentFrom, 'student_version_to' => $studentTo,
                'status' => 'inserted',
            ]);
        });
    }

    /** Annulla un inserimento: ripristina course_sources, sezione live e modules.content a PRIMA. */
    public function revert(GapInsertion $insertion): GapInsertion
    {
        return DB::transaction(function () use ($insertion) {
            if ($insertion->status === 'reverted') {
                return $insertion;
            }
            $course = $insertion->course;

            // FORMATORE: nuova versione = copia dei blocchi PRE-inserimento (append-only).
            $pre = CourseSource::where('course_id', $course->id)->where('version', $insertion->formatore_version_from)->first();
            if ($pre) {
                $latest = CourseSource::where('course_id', $course->id)->orderByDesc('created_at')->orderByDesc('id')->first();
                $revVer = $this->nextMinor($latest->version);
                CourseSource::create(['course_id' => $course->id, 'version' => $revVer, 'blocks' => $pre->blocks]);
                CourseChangelog::create(['course_id' => $course->id, 'content_source' => 'instructor',
                    'version_from' => $insertion->formatore_version_to, 'version_to' => $revVer, 'kind' => 'revert',
                    'summary' => 'Annullamento inserimento (gap)']);
            }

            // FORMATORE live: ripristina il content_html della sezione toccata.
            if ($insertion->instructor_section_id && $insertion->instructor_section_html_before !== null) {
                InstructorManualSection::where('id', $insertion->instructor_section_id)
                    ->update(['content_html' => $insertion->instructor_section_html_before]);
            }

            // STUDENTE: ripristina modules.content dallo snapshot PRE-inserimento + nuova versione.
            if ($insertion->student_version_from) {
                $preStud = StudentSourceVersion::where('course_id', $course->id)->where('version', $insertion->student_version_from)->first();
                if ($preStud) {
                    foreach ($preStud->content as $item) {
                        Module::where('id', $item['module_id'])->update(['content' => $item['content_html']]);
                    }
                    $latest = $this->latestStudentVersion($course->id);
                    $revVer = $this->nextMinor($latest->version);
                    StudentSourceVersion::create(['course_id' => $course->id, 'version' => $revVer, 'content' => $preStud->content]);
                    CourseChangelog::create(['course_id' => $course->id, 'content_source' => 'student',
                        'version_from' => $insertion->student_version_to, 'version_to' => $revVer, 'kind' => 'revert',
                        'summary' => 'Annullamento inserimento studente (gap)']);
                }
            }

            $insertion->update(['status' => 'reverted']);

            return $insertion;
        });
    }

    private function indexOfBlock(array $blocks, string $id): ?int
    {
        foreach ($blocks as $i => $b) {
            if (($b['id'] ?? null) === $id) {
                return $i;
            }
        }
        return null;
    }

    private function blockText(array $block): string
    {
        $text = trim((string) ($block['text'] ?? ''));
        if ($text === '' && isset($block['items']) && is_array($block['items'])) {
            $text = trim(implode(' ', array_map('strval', $block['items'])));
        }
        return $text;
    }

    private function sectionFor(string $courseId, string $anchorText): ?InstructorManualSection
    {
        $snippet = mb_substr($anchorText, 0, 60);
        if (trim($snippet) === '') {
            return null;
        }
        foreach (InstructorManualSection::where('course_id', $courseId)->get() as $s) {
            $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $s->content_html)));
            if (mb_strpos($plain, $snippet) !== false) {
                return $s;
            }
        }
        return null;
    }

    private function ensureStudentBaseline(string $courseId): string
    {
        $current = $this->latestStudentVersion($courseId);
        if ($current) {
            return $current->version;
        }
        // Nessuna versione studente ancora: la live attuale (PRE-inserimento) è "1.0".
        StudentSourceVersion::create(['course_id' => $courseId, 'version' => '1.0', 'content' => $this->studentSnapshot($courseId)]);

        return '1.0';
    }

    private function latestStudentVersion(string $courseId): ?StudentSourceVersion
    {
        return StudentSourceVersion::where('course_id', $courseId)
            ->orderByDesc('created_at')->orderByDesc('id')->first();
    }

    /** @return list<array{module_id:string, content_html:string}> */
    private function studentSnapshot(string $courseId): array
    {
        $out = [];
        foreach (Module::where('course_id', $courseId)->orderBy('sort_order')->get() as $m) {
            $out[] = ['module_id' => $m->id, 'content_html' => (string) $m->content];
        }
        return $out;
    }

    private function nextMinor(string $v): string
    {
        if (preg_match('/^(\d+)\.(\d+)$/', $v, $m)) {
            return $m[1] . '.' . ((int) $m[2] + 1);
        }
        if (preg_match('/^\d+$/', $v)) {
            return $v . '.1';
        }
        throw new RuntimeException("Versione non incrementabile: {$v}");
    }
}
