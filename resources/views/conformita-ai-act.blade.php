@extends('layouts.app')

@section('title', 'Conformità EU AI Act — Track AI Noscite')
@section('description', 'Analisi di copertura dei corsi PRIMUS · CONSILIUM · INITIUM rispetto all\'obbligo di alfabetizzazione AI dell\'EU AI Act (Reg. UE 2024/1689, Art. 4). Documento Noscite di compliance e posizionamento commerciale.')
@section('og_title', 'Conformità EU AI Act — Track AI Noscite')
@section('og_description', 'Analisi di copertura dei tre corsi del percorso lineare PRIMUS → CONSILIUM → INITIUM rispetto alle quattro aree dell\'alfabetizzazione AI richieste dall\'AI Act.')

@push('styles')
<style>
.compliance-page { font-family: Calibri, "Segoe UI", system-ui, sans-serif; }
/* Pill helpers (a11y: aria-label inline nei tag) */
.pill-full    { background:#D1FAE5; color:#059669; font-weight:700; padding:4px 10px; border-radius:4px; display:inline-block; min-width:56px; text-align:center; font-size:13px; }
.pill-partial { background:#FEF3C7; color:#D97706; font-weight:700; padding:4px 10px; border-radius:4px; display:inline-block; min-width:56px; text-align:center; font-size:13px; }
.pill-na      { background:#F3F4F6; color:#9CA3AF; font-weight:700; padding:4px 10px; border-radius:4px; display:inline-block; min-width:56px; text-align:center; font-size:13px; }
.pill-mini-full    { background:#D1FAE5; color:#059669; font-weight:700; padding:2px 8px; border-radius:4px; font-size:11px; }
.pill-mini-partial { background:#FEF3C7; color:#D97706; font-weight:700; padding:2px 8px; border-radius:4px; font-size:11px; }
.pill-mini-na      { background:#F3F4F6; color:#9CA3AF; font-weight:700; padding:2px 8px; border-radius:4px; font-size:11px; }

.sr-only { position:absolute !important; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }

@media print {
  header.fixed, nav, footer, .cookie-banner, .no-print { display: none !important; }
  body { background: #FFFFFF !important; padding: 0; }
  .compliance-page { background: #FFFFFF !important; padding: 0; }
  .compliance-card { box-shadow: none !important; border: 1px solid #E5E7EB; page-break-inside: avoid; }
  table { page-break-inside: avoid; }
  thead { display: table-header-group; }
  tr { page-break-inside: avoid; }
  .pill-full, .pill-partial, .pill-na,
  .pill-mini-full, .pill-mini-partial, .pill-mini-na,
  .compliance-card, .compliance-bg-tealLight, .compliance-bg-primus, .compliance-bg-initium {
    -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
  }
  a[href]::after { content: ""; }
}
</style>
@endpush

@section('content')
<div class="compliance-page min-h-screen" style="background:#F5F7F7;">
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

  {{-- ===== HERO ===== --}}
  <div class="compliance-card bg-white rounded-lg shadow-sm p-7 mb-5" style="border-left:6px solid #55B1AE;">
    <div class="text-xs tracking-[1.5px] uppercase font-bold mb-2" style="color:#E28A53;">
      EU AI Act — Reg. UE 2024/1689 · Art. 4 (Alfabetizzazione AI)
    </div>
    <h1 class="text-3xl md:text-4xl font-bold leading-tight mb-1" style="color:#1A1F1F;">
      Track AI Noscite — Conformità all'AI Act
    </h1>
    <p class="text-base" style="color:#4A5252;">
      Analisi di copertura dei tre corsi del percorso lineare: <strong>PRIMUS → CONSILIUM → INITIUM</strong>
    </p>
    <p class="italic text-sm mt-3" style="color:#E28A53;">In digitālī nova virtūs</p>
  </div>

  {{-- ===== LEGENDA: 4 AREE + SIMBOLI ===== --}}
  <div class="compliance-card bg-white rounded-lg shadow-sm p-6 mb-5">
    <h2 class="text-xl font-bold mb-4" style="color:#3D8B88;">Le quattro aree dell'alfabetizzazione AI (Art. 4)</h2>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
      <div class="rounded-md p-3" style="background:#E8F5F5; border-top:3px solid #55B1AE;">
        <div class="text-xl font-bold" style="color:#3D8B88;">CT</div>
        <div class="text-sm font-semibold mt-0.5" style="color:#1A1F1F;">Comprensione Tecnologica</div>
        <div class="text-xs mt-1" style="color:#8A9696;">Funzionamento LLM/agenti, bias, allucinazioni, limiti, architetture</div>
      </div>
      <div class="rounded-md p-3" style="background:#E8F5F5; border-top:3px solid #55B1AE;">
        <div class="text-xl font-bold" style="color:#3D8B88;">CA</div>
        <div class="text-sm font-semibold mt-0.5" style="color:#1A1F1F;">Conoscenza Applicativa</div>
        <div class="text-xs mt-1" style="color:#8A9696;">Uso consapevole negli strumenti e processi aziendali reali</div>
      </div>
      <div class="rounded-md p-3" style="background:#E8F5F5; border-top:3px solid #55B1AE;">
        <div class="text-xl font-bold" style="color:#3D8B88;">PC</div>
        <div class="text-sm font-semibold mt-0.5" style="color:#1A1F1F;">Pensiero Critico</div>
        <div class="text-xs mt-1" style="color:#8A9696;">Valutazione rischi, supervisione umana, validazione output, etica</div>
      </div>
      <div class="rounded-md p-3" style="background:#E8F5F5; border-top:3px solid #55B1AE;">
        <div class="text-xl font-bold" style="color:#3D8B88;">CN</div>
        <div class="text-sm font-semibold mt-0.5" style="color:#1A1F1F;">Conformità Normativa</div>
        <div class="text-xs mt-1" style="color:#8A9696;">GDPR, AI Act, policy, DPA, HITL, documentazione per audit</div>
      </div>
    </div>

    <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
      <span class="inline-flex items-center gap-2">
        <span class="pill-mini-full" aria-hidden="true">✓ PIENA</span>
        <span style="color:#4A5252;">copertura diretta ed esplicita del requisito</span>
      </span>
      <span class="inline-flex items-center gap-2">
        <span class="pill-mini-partial" aria-hidden="true">◑ PARZIALE</span>
        <span style="color:#4A5252;">contribuisce ma non in modo esaustivo</span>
      </span>
      <span class="inline-flex items-center gap-2">
        <span class="pill-mini-na" aria-hidden="true">— N.A.</span>
        <span style="color:#4A5252;">non applicabile agli obiettivi del corso</span>
      </span>
    </div>
  </div>

  {{-- ===== TABELLA SINTESI ===== --}}
  <div class="compliance-card bg-white rounded-lg shadow-sm mb-5 overflow-hidden">
    <h2 class="text-xl font-bold px-6 pt-6 pb-3" style="color:#3D8B88;">Sintesi — copertura delle 4 aree per corso</h2>

    <div class="overflow-x-auto">
      <table class="w-full border-collapse text-sm" role="table" aria-label="Sintesi copertura Art. 4 per corso">
        <thead>
          <tr class="text-xs uppercase tracking-wider text-white" style="background:#55B1AE;">
            <th scope="col" class="text-left p-3 font-semibold">Corso</th>
            <th scope="col" class="p-3 font-semibold"><abbr class="no-underline" title="Comprensione Tecnologica">CT</abbr></th>
            <th scope="col" class="p-3 font-semibold"><abbr class="no-underline" title="Conoscenza Applicativa">CA</abbr></th>
            <th scope="col" class="p-3 font-semibold"><abbr class="no-underline" title="Pensiero Critico">PC</abbr></th>
            <th scope="col" class="p-3 font-semibold"><abbr class="no-underline" title="Conformità Normativa">CN</abbr></th>
            <th scope="col" class="hidden md:table-cell text-left p-3 font-semibold">Posizionamento rispetto all'Art. 4</th>
          </tr>
        </thead>
        <tbody>
          {{-- PRIMUS — muted --}}
          <tr class="border-b border-gray-200 align-top" style="background:#FBFBFC;">
            <th scope="row" class="p-3 text-left font-normal">
              <div class="font-bold" style="color:#1A1F1F;">PRIMUS</div>
              <div class="text-xs" style="color:#8A9696;">Awareness · 4h · 4 moduli</div>
            </th>
            <td class="p-3 text-center"><span class="pill-na" aria-label="Non applicabile">—<span class="sr-only"> Non applicabile</span></span></td>
            <td class="p-3 text-center"><span class="pill-na" aria-label="Non applicabile">—<span class="sr-only"> Non applicabile</span></span></td>
            <td class="p-3 text-center"><span class="pill-na" aria-label="Non applicabile">—<span class="sr-only"> Non applicabile</span></span></td>
            <td class="p-3 text-center"><span class="pill-na" aria-label="Non applicabile">—<span class="sr-only"> Non applicabile</span></span></td>
            <td class="hidden md:table-cell p-3 text-xs max-w-[280px]" style="color:#4A5252;">
              <strong style="color:#E28A53;">Pre-compliance.</strong> Workshop di awareness che precede la literacy formalizzata: crea la consapevolezza del gap digitale, ma non eroga ancora i contenuti Art. 4. Prepara il terreno a CONSILIUM e INITIUM.
            </td>
          </tr>
          {{-- CONSILIUM --}}
          <tr class="bg-white border-b border-gray-200 align-top">
            <th scope="row" class="p-3 text-left font-normal">
              <div class="font-bold" style="color:#1A1F1F;">CONSILIUM</div>
              <div class="text-xs" style="color:#8A9696;">Strategia AI · 7h · 4 moduli</div>
            </th>
            <td class="p-3 text-center"><span class="pill-partial" aria-label="Parziale, 1 modulo su 4">◑ 1/4<span class="sr-only"> Parziale</span></span></td>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena, 2 moduli su 4">✓ 2/4<span class="sr-only"> Piena</span></span></td>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena, 2 moduli su 4">✓ 2/4<span class="sr-only"> Piena</span></span></td>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena, 3 moduli su 4">✓ 3/4<span class="sr-only"> Piena</span></span></td>
            <td class="hidden md:table-cell p-3 text-xs max-w-[280px]" style="color:#4A5252;">
              Literacy <strong>direzionale</strong>. La CT è approfondita solo nel Modulo 1 (scenario AI), perché il board non necessita di profondità tecnica su ogni modulo. La CN è presidiata su 3 moduli su 4 (campo normativo nei canvas + AI Usage Policy).
            </td>
          </tr>
          {{-- INITIUM — highlight --}}
          <tr class="border-b border-gray-200 align-top" style="background:#E8F5F5;">
            <th scope="row" class="p-3 text-left font-normal">
              <div class="font-bold" style="color:#1A1F1F;">INITIUM</div>
              <div class="text-xs" style="color:#8A9696;">Fondamenta AI Operativa · 20h + 3h esame · 5 moduli · v6</div>
            </th>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena, 4 moduli su 5">✓ 4/5<span class="sr-only"> Piena</span></span></td>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena, 5 moduli su 5">✓ 5/5<span class="sr-only"> Piena</span></span></td>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena, 5 moduli su 5">✓ 5/5<span class="sr-only"> Piena</span></span></td>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena, 5 moduli su 5">✓ 5/5<span class="sr-only"> Piena</span></span></td>
            <td class="hidden md:table-cell p-3 text-xs max-w-[280px]" style="color:#4A5252;">
              <strong style="color:#3D8B88;">Compliance primaria Art. 4.</strong> Copre tutte e quattro le aree in tutti i moduli. La versione 6 integra i due pilastri (Cap 0) e un blocco normativo esplicito nel Modulo 5 (rischio, Art. 5, Art. 50). Corso obbligatorio per ogni dipendente che usa ChatGPT, Copilot o Claude come deployer.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    {{-- Fallback mobile: card-stack riassuntivo del posizionamento --}}
    <div class="md:hidden px-6 py-4 space-y-3 text-xs" style="color:#4A5252;">
      <div class="p-3 rounded" style="background:#FBFBFC;">
        <div class="font-bold mb-1" style="color:#1A1F1F;">PRIMUS</div>
        <strong style="color:#E28A53;">Pre-compliance.</strong> Workshop di awareness che precede la literacy formalizzata: prepara il terreno a CONSILIUM e INITIUM.
      </div>
      <div class="p-3 rounded bg-white border border-gray-200">
        <div class="font-bold mb-1" style="color:#1A1F1F;">CONSILIUM</div>
        Literacy <strong>direzionale</strong>. CT approfondita nel Modulo 1; CN presidiata su 3 moduli su 4.
      </div>
      <div class="p-3 rounded" style="background:#E8F5F5;">
        <div class="font-bold mb-1" style="color:#1A1F1F;">INITIUM</div>
        <strong style="color:#3D8B88;">Compliance primaria Art. 4.</strong> Copre tutte e quattro le aree in tutti i moduli (v6).
      </div>
    </div>
  </div>

  {{-- ===== BOX NOTA PRIMUS ===== --}}
  <div class="compliance-card compliance-bg-primus rounded-md p-5 mb-5 text-sm" style="background:#FBF6F1; border-left:4px solid #E28A53; color:#4A5252;">
    <strong style="color:#E28A53;">Perché PRIMUS è a "—" su tutte le aree.</strong> Non è una lacuna: è una scelta di onestà metodologica. L'Art. 4 richiede alfabetizzazione AI; PRIMUS opera a monte, sul riconoscimento del bisogno. Indicarlo come pre-compliance è più solido — e più forte commercialmente — che attribuirgli una copertura fittizia. La compliance formale inizia con CONSILIUM (direzione) e si completa con INITIUM (tutto il personale deployer).
  </div>

  {{-- ===== BOX NOTA INITIUM ===== --}}
  <div class="compliance-card compliance-bg-initium rounded-md p-5 mb-5 text-sm" style="background:#E8F5F5; border-left:4px solid #55B1AE; color:#4A5252;">
    <strong style="color:#3D8B88;">INITIUM è il corso di compliance primaria.</strong> Per una PMI che deve formalizzare l'obbligo Art. 4 sui dipendenti che usano strumenti AI generativi, INITIUM è il percorso che lo copre pienamente. Con la versione 6 i due pilastri — Umanesimo Digitale e human-in-the-loop — sono espliciti (Cap 0) e il Modulo 5 affronta direttamente il quadro normativo dell'AI Act. CONSILIUM presidia il livello strategico-direzionale a monte.
  </div>

  {{-- ===== DETTAGLIO CONSILIUM ===== --}}
  <div class="compliance-card bg-white rounded-lg shadow-sm mb-5 overflow-hidden">
    <h2 class="text-xl font-bold px-6 pt-6 pb-3" style="color:#3D8B88;">Dettaglio analitico — CONSILIUM (7h, 4 moduli)</h2>

    <div class="overflow-x-auto">
      <table class="w-full border-collapse text-sm" role="table" aria-label="Dettaglio analitico CONSILIUM per modulo">
        <thead>
          <tr class="text-xs uppercase tracking-wider text-white" style="background:#3D8B88;">
            <th scope="col" class="text-left p-3 font-semibold">Modulo</th>
            <th scope="col" class="p-3 font-semibold"><abbr class="no-underline" title="Comprensione Tecnologica">CT</abbr></th>
            <th scope="col" class="p-3 font-semibold"><abbr class="no-underline" title="Conoscenza Applicativa">CA</abbr></th>
            <th scope="col" class="p-3 font-semibold"><abbr class="no-underline" title="Pensiero Critico">PC</abbr></th>
            <th scope="col" class="p-3 font-semibold"><abbr class="no-underline" title="Conformità Normativa">CN</abbr></th>
            <th scope="col" class="hidden md:table-cell text-left p-3 font-semibold">Contenuti rilevanti per compliance</th>
          </tr>
        </thead>
        <tbody>
          <tr class="border-b border-gray-200 align-top">
            <th scope="row" class="p-3 text-left font-normal"><strong>M1</strong> — Scenario AI per PMI <span class="text-xs block" style="color:#8A9696;">(1h30)</span></th>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena">✓<span class="sr-only"> Piena</span></span></td>
            <td class="p-3 text-center"><span class="pill-partial" aria-label="Parziale">◑<span class="sr-only"> Parziale</span></span></td>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena">✓<span class="sr-only"> Piena</span></span></td>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena">✓<span class="sr-only"> Piena</span></span></td>
            <td class="hidden md:table-cell p-3 text-xs" style="color:#4A5252;">Funzionamento LLM, allucinazioni, bias strutturali, limiti AI; rischi GDPR e vendor lock-in; primo presidio normativo per i decision maker.</td>
          </tr>
          <tr class="border-b border-gray-200 align-top">
            <th scope="row" class="p-3 text-left font-normal"><strong>M2</strong> — Mappatura processi e casi d'uso <span class="text-xs block" style="color:#8A9696;">(2h)</span></th>
            <td class="p-3 text-center"><span class="pill-na" aria-label="Non applicabile">—<span class="sr-only"> Non applicabile</span></span></td>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena">✓<span class="sr-only"> Piena</span></span></td>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena">✓<span class="sr-only"> Piena</span></span></td>
            <td class="p-3 text-center"><span class="pill-partial" aria-label="Parziale">◑<span class="sr-only"> Parziale</span></span></td>
            <td class="hidden md:table-cell p-3 text-xs" style="color:#4A5252;">Criteri di candidatura AI; matrice impatto/fattibilità; campo normativo nei canvas (GDPR/AI Act per ogni caso d'uso).</td>
          </tr>
          <tr class="border-b border-gray-200 align-top">
            <th scope="row" class="p-3 text-left font-normal"><strong>M3</strong> — Selezione 3 progetti prioritari <span class="text-xs block" style="color:#8A9696;">(1h30)</span></th>
            <td class="p-3 text-center"><span class="pill-na" aria-label="Non applicabile">—<span class="sr-only"> Non applicabile</span></span></td>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena">✓<span class="sr-only"> Piena</span></span></td>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena">✓<span class="sr-only"> Piena</span></span></td>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena">✓<span class="sr-only"> Piena</span></span></td>
            <td class="hidden md:table-cell p-3 text-xs" style="color:#4A5252;">Schede progetto con owner, KPI, rischi; campo "Implicazioni normative" per ogni progetto prioritario; deliverable rilevante per audit.</td>
          </tr>
          <tr class="border-b border-gray-200 align-top">
            <th scope="row" class="p-3 text-left font-normal"><strong>M4</strong> — AI Usage Policy e Roadmap <span class="text-xs block" style="color:#8A9696;">(2h)</span></th>
            <td class="p-3 text-center"><span class="pill-na" aria-label="Non applicabile">—<span class="sr-only"> Non applicabile</span></span></td>
            <td class="p-3 text-center"><span class="pill-partial" aria-label="Parziale">◑<span class="sr-only"> Parziale</span></span></td>
            <td class="p-3 text-center"><span class="pill-partial" aria-label="Parziale">◑<span class="sr-only"> Parziale</span></span></td>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena">✓<span class="sr-only"> Piena</span></span></td>
            <td class="hidden md:table-cell p-3 text-xs" style="color:#4A5252;">Policy: classificazione dati 3 livelli, piattaforme autorizzate, verifica output, responsabilità; roadmap 90gg. Primo atto di compliance organizzativa.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  {{-- ===== DETTAGLIO INITIUM v6 ===== --}}
  <div class="compliance-card bg-white rounded-lg shadow-sm mb-5 overflow-hidden">
    <h2 class="text-xl font-bold px-6 pt-6 pb-3" style="color:#3D8B88;">Dettaglio analitico — INITIUM v6 (20h + 3h esame, 5 moduli)</h2>

    <div class="overflow-x-auto">
      <table class="w-full border-collapse text-sm" role="table" aria-label="Dettaglio analitico INITIUM v6 per modulo">
        <thead>
          <tr class="text-xs uppercase tracking-wider text-white" style="background:#3D8B88;">
            <th scope="col" class="text-left p-3 font-semibold">Modulo</th>
            <th scope="col" class="p-3 font-semibold"><abbr class="no-underline" title="Comprensione Tecnologica">CT</abbr></th>
            <th scope="col" class="p-3 font-semibold"><abbr class="no-underline" title="Conoscenza Applicativa">CA</abbr></th>
            <th scope="col" class="p-3 font-semibold"><abbr class="no-underline" title="Pensiero Critico">PC</abbr></th>
            <th scope="col" class="p-3 font-semibold"><abbr class="no-underline" title="Conformità Normativa">CN</abbr></th>
            <th scope="col" class="hidden md:table-cell text-left p-3 font-semibold">Contenuti rilevanti per compliance</th>
          </tr>
        </thead>
        <tbody>
          {{-- Cap 0 trasversale --}}
          <tr class="compliance-bg-primus border-b border-gray-200 align-top" style="background:#FBF6F1;">
            <td colspan="6" class="p-3 text-xs" style="color:#4A5252;">
              <strong style="color:#E28A53;">Cap 0 (trasversale) — I due pilastri:</strong> Umanesimo Digitale (antropocentrismo Art. 1(1) AI Act + Art. 3 L. 132/2025) e human-in-the-loop sostanziale (4 caratteristiche: tempo, competenza, autorità, supporto). Richiamati nei Moduli 2, 4 e 5 e verificati in esame (domande 21-23). Rafforzano trasversalmente Pensiero Critico e Conformità Normativa.
            </td>
          </tr>
          <tr class="border-b border-gray-200 align-top">
            <th scope="row" class="p-3 text-left font-normal"><strong>M1</strong> — Capire l'AI: logica, dati e limiti <span class="text-xs block" style="color:#8A9696;">(4h)</span></th>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena">✓<span class="sr-only"> Piena</span></span></td>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena">✓<span class="sr-only"> Piena</span></span></td>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena">✓<span class="sr-only"> Piena</span></span></td>
            <td class="p-3 text-center"><span class="pill-partial" aria-label="Parziale">◑<span class="sr-only"> Parziale</span></span></td>
            <td class="hidden md:table-cell p-3 text-xs" style="color:#4A5252;">Pre-training e RLHF, Stanza Cinese, allucinazioni, data cutoff, bias strutturali; bias cognitivi; social engineering AI; Human-AI Security Awareness.</td>
          </tr>
          <tr class="border-b border-gray-200 align-top">
            <th scope="row" class="p-3 text-left font-normal"><strong>M2</strong> — Prompt Engineering e Perplexity <span class="text-xs block" style="color:#8A9696;">(4h)</span></th>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena">✓<span class="sr-only"> Piena</span></span></td>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena">✓<span class="sr-only"> Piena</span></span></td>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena">✓<span class="sr-only"> Piena</span></span></td>
            <td class="p-3 text-center"><span class="pill-partial" aria-label="Parziale">◑<span class="sr-only"> Parziale</span></span></td>
            <td class="hidden md:table-cell p-3 text-xs" style="color:#4A5252;">Framework COFT; prompting sicuro (3 domande pre-prompt, anonimizzazione dati, protezione da prompt injection); ricerca verificata con fonti.</td>
          </tr>
          <tr class="border-b border-gray-200 align-top">
            <th scope="row" class="p-3 text-left font-normal"><strong>M3</strong> — Claude e ChatGPT: analisi, contenuti, automazioni <span class="text-xs block" style="color:#8A9696;">(4h)</span></th>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena">✓<span class="sr-only"> Piena</span></span></td>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena">✓<span class="sr-only"> Piena</span></span></td>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena">✓<span class="sr-only"> Piena</span></span></td>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena">✓<span class="sr-only"> Piena</span></span></td>
            <td class="hidden md:table-cell p-3 text-xs" style="color:#4A5252;">Profilo comparativo dei modelli; workflow combinato su caso aziendale; matrice di classificazione dati a 3 livelli con regole per strumento.</td>
          </tr>
          <tr class="border-b border-gray-200 align-top">
            <th scope="row" class="p-3 text-left font-normal"><strong>M4</strong> — Vibe Coding e Claude oltre il browser <span class="text-xs block" style="color:#8A9696;">(4h)</span></th>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena">✓<span class="sr-only"> Piena</span></span></td>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena">✓<span class="sr-only"> Piena</span></span></td>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena">✓<span class="sr-only"> Piena</span></span></td>
            <td class="p-3 text-center"><span class="pill-partial" aria-label="Parziale">◑<span class="sr-only"> Parziale</span></span></td>
            <td class="hidden md:table-cell p-3 text-xs" style="color:#4A5252;">Checklist sicurezza codice AI-generated; automazioni; Cowork e connettori MCP; approvazione esplicita delle azioni agentiche (HITL); cybersecurity applicata.</td>
          </tr>
          <tr class="border-b border-gray-200 align-top">
            <th scope="row" class="p-3 text-left font-normal"><strong>M5</strong> — Second Brain, Data Governance e Private AI <span class="text-xs block" style="color:#8A9696;">(4h)</span></th>
            <td class="p-3 text-center"><span class="pill-partial" aria-label="Parziale">◑<span class="sr-only"> Parziale</span></span></td>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena">✓<span class="sr-only"> Piena</span></span></td>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena">✓<span class="sr-only"> Piena</span></span></td>
            <td class="p-3 text-center"><span class="pill-full" aria-label="Piena">✓<span class="sr-only"> Piena</span></span></td>
            <td class="hidden md:table-cell p-3 text-xs" style="color:#4A5252;">AI Usage Policy (5 pilastri); GDPR con DPIA e trasferimento extra-UE; Private AI; <strong>e (v6) il quadro normativo dell'AI Act: classificazione del rischio e Allegato III, pratiche vietate Art. 5, obblighi di trasparenza Art. 50, ruolo di deployer e timeline.</strong></td>
          </tr>
          <tr class="border-b border-gray-200 align-top">
            <th scope="row" class="p-3 text-left font-normal"><strong>Esame</strong> — Certified AI Productivity User <span class="text-xs block" style="color:#8A9696;">(3h)</span></th>
            <td colspan="4" class="p-3 text-center text-xs italic" style="color:#8A9696;">Assessment integrato su tutte e 4 le aree</td>
            <td class="hidden md:table-cell p-3 text-xs" style="color:#4A5252;">Scenario aziendale reale: workflow multi-strumento, checklist sicurezza, AI Usage Policy, roadmap 90gg. Domande 21-23 di credito aggiuntivo sui due pilastri e sul quadro AI Act. Soglia 70/100.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  {{-- ===== CTA finale ===== --}}
  <div class="compliance-card rounded-lg p-7 mb-5 text-center no-print" style="background:#E8F5F5; border:1px solid #55B1AE;">
    <h3 class="text-lg font-bold mb-2" style="color:#3D8B88;">Vuoi sapere a che punto è la tua azienda?</h3>
    <p class="mb-5 max-w-2xl mx-auto" style="color:#4A5252;">
      Compila in 3 minuti la <strong>Mappa di Maturità AI</strong> e ricevi via email il report PDF personalizzato con la raccomandazione del corso più adatto al tuo livello attuale.
    </p>
    <div class="flex flex-wrap justify-center gap-3">
      <a href="https://noscite.it/assessment-ai-act" target="_blank" rel="noopener"
         class="inline-block px-7 py-3 text-white rounded font-bold transition hover:opacity-90"
         style="background:#E28A53;">
        Compila la Mappa di Maturità AI
      </a>
      <a href="{{ route('contatti') }}"
         class="inline-block px-7 py-3 text-white rounded font-bold transition hover:opacity-90"
         style="background:#55B1AE;">
        Contattaci per un percorso su misura
      </a>
    </div>
  </div>

  {{-- ===== FOOTER PAGINA ===== --}}
  <div class="text-center text-xs py-5" style="color:#8A9696;">
    <div>Track AI Noscite · Conformità EU AI Act Art. 4 · coerente con INITIUM v6 e con la Mappa di Conformità (Reg. UE 2024/1689) · Maggio 2026</div>
    <div class="italic mt-1" style="color:#E28A53;">In digitālī nova virtūs</div>
    <div class="mt-1">Documento per uso commerciale e compliance · Conservare per audit AI Act (min. 5 anni)</div>
  </div>

</div>
</div>
@endsection
