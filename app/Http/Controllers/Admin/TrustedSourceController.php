<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\TrustedSource;
use App\Services\SourceSuggester;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * P26 Fase 0 — Registro delle fonti attendibili (CRUD admin) + proposta-fonti assistita.
 * Gated da config('services.p26.enabled'): se off, tutti gli endpoint rispondono 404.
 * HITL: una fonte diventa 'approved' SOLO per azione admin (o store manuale, atto di fiducia).
 * Additivo: non tocca P25/B/F, mai corsi/moduli/studenti.
 */
class TrustedSourceController extends Controller
{
    public function __construct()
    {
        // Gating globale: la feature è opt-in finché non è pronta.
        abort_unless(config('services.p26.enabled'), 404);
    }

    public function index(Request $request)
    {
        $filterTopic = $request->query('topic') ?: null;
        $filterStatus = in_array($request->query('status'), ['suggested', 'approved', 'rejected'], true)
            ? $request->query('status') : null;

        $sources = TrustedSource::topic($filterTopic)
            ->status($filterStatus)
            ->orderBy('topic')
            ->orderByRaw("array_position(ARRAY['suggested','approved','rejected']::text[], status)")
            ->orderBy('label')
            ->get()
            ->groupBy('topic');

        $topics = TrustedSource::query()->distinct()->orderBy('topic')->pluck('topic');

        return view('admin.sources.index', compact('sources', 'topics', 'filterTopic', 'filterStatus'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => 'required|string|max:255',
            'url_or_domain' => 'required|string|max:1000',
            'mode' => 'required|in:search,fetch',
            'topic' => 'required|string|max:120',
            'notes' => 'nullable|string|max:500',
        ]);

        // Normalizza/valida il target: una fonte malformata non deve entrare (romperebbe lo Scout).
        $norm = SourceSuggester::normalizeTarget($data['mode'], $data['url_or_domain']);
        if (!$norm['ok']) {
            return back()->withInput()->with('error', $norm['error']);
        }

        // Topic SEMPRE slugificato → coerente coi topic dei corsi (altrimenti lo Scout non li trova).
        $topic = Str::slug($data['topic']);
        if ($topic === '') {
            return back()->withInput()->with('error', 'Topic non valido.');
        }

        // UNIQUE(topic,url_or_domain,mode): evita doppioni con un messaggio chiaro.
        $dup = TrustedSource::where('topic', $topic)
            ->where('url_or_domain', $norm['value'])->where('mode', $data['mode'])->exists();
        if ($dup) {
            return back()->withInput()->with('error', 'Esiste già una fonte con questo dominio/URL per il topic.');
        }

        // Aggiunta manuale dall'admin = atto di fiducia → nasce già 'approved' (con audit).
        TrustedSource::create([
            'label' => $data['label'],
            'url_or_domain' => $norm['value'],
            'mode' => $data['mode'],
            'topic' => $topic,
            'notes' => $data['notes'] ?? null,
            'status' => 'approved',
            'proposed_by' => 'admin',
            'reviewed_by' => $this->adminId(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Fonte aggiunta e approvata.');
    }

    public function suggest(Request $request, SourceSuggester $suggester)
    {
        $topic = Str::slug((string) $request->input('topic', ''));
        if ($topic === '') {
            return back()->with('error', 'Indica un topic per cui proporre fonti.');
        }

        // Isolamento: il suggester può fallire (es. credito esaurito) senza intaccare il registro.
        try {
            $res = $suggester->suggest($topic);
        } catch (\Throwable $e) {
            return back()->with('error', 'Proposta fonti non riuscita: ' . $e->getMessage());
        }

        return back()->with('success', "Proposte {$res['created']} fonti per «{$topic}» "
            . "(saltate {$res['skipped']} già presenti). Rivedile e approva quelle valide.");
    }

    public function approve(TrustedSource $source)
    {
        $source->update(['status' => 'approved', 'reviewed_by' => $this->adminId(), 'reviewed_at' => now()]);

        return back()->with('success', "Fonte «{$source->label}» approvata: lo Scout la userà.");
    }

    public function reject(TrustedSource $source)
    {
        $source->update(['status' => 'rejected', 'reviewed_by' => $this->adminId(), 'reviewed_at' => now()]);

        return back()->with('success', "Fonte «{$source->label}» rifiutata: non verrà ri-proposta.");
    }

    public function destroy(TrustedSource $source)
    {
        $source->delete();

        return back()->with('success', 'Fonte rimossa.');
    }

    private function adminId(): ?string
    {
        return Admin::where('email', session('admin_email'))->value('id');
    }
}
