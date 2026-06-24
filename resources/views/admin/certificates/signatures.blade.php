@extends('layouts.admin')

@section('title', 'Firma Certificati')

@section('content')
<div class="space-y-6">

    {{-- session('success') è già renderizzato dal layout admin globale --}}

    @if (session('info'))
        <div class="rounded-lg p-4 text-sm" style="background:#E8F0F5; border:1px solid #B5D0DD; color:#3A6C8C;">
            &#9432; {{ session('info') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-lg p-4 text-sm" style="background:#FCEBEB; border:1px solid #F0BFBF; color:#9C3030;">
            <strong>Errore:</strong>
            <ul class="mt-2 list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Report batch upload (se presente) --}}
    @if (session('batch_report'))
        @php $report = session('batch_report'); @endphp
        <div class="rounded-lg bg-white p-5 space-y-3" style="border:1px solid #C8D0D0;">
            <h3 class="text-base font-bold" style="color:#1A1F1F;">Report ultimo batch upload</h3>
            <div class="grid sm:grid-cols-5 gap-3 text-sm">
                <div class="rounded-lg p-3" style="background:#E8F5F5; border:1px solid #B5DDDC;">
                    <div class="font-bold text-2xl" style="color:#3A8C89;">{{ count($report['success']) }}</div>
                    <div class="text-xs" style="color:#3A8C89;">Firmati con successo</div>
                </div>
                <div class="rounded-lg p-3" style="background:#fff3ec; border:1px solid #F0CDA8;">
                    <div class="font-bold text-2xl" style="color:#c97a45;">{{ count($report['orphan']) }}</div>
                    <div class="text-xs" style="color:#c97a45;">File orfani (codice ignoto)</div>
                </div>
                <div class="rounded-lg p-3" style="background:#fff3ec; border:1px solid #F0CDA8;">
                    <div class="font-bold text-2xl" style="color:#c97a45;">{{ count($report['invalid_pdf']) }}</div>
                    <div class="text-xs" style="color:#c97a45;">PDF non validi</div>
                </div>
                <div class="rounded-lg p-3" style="background:#fff3ec; border:1px solid #F0CDA8;">
                    <div class="font-bold text-2xl" style="color:#c97a45;">{{ count($report['unsigned_match']) }}</div>
                    <div class="text-xs" style="color:#c97a45;">File non firmati</div>
                </div>
                <div class="rounded-lg p-3" style="background:#FCEBEB; border:1px solid #F0BFBF;">
                    <div class="font-bold text-2xl" style="color:#9C3030;">{{ count($report['errors']) }}</div>
                    <div class="text-xs" style="color:#9C3030;">Errori</div>
                </div>
            </div>

            @if (!empty($report['orphan']) || !empty($report['unsigned_match']) || !empty($report['errors']))
                <details class="text-xs" style="color:#4A5252;">
                    <summary class="cursor-pointer font-medium">Dettagli</summary>
                    <div class="mt-2 space-y-2">
                        @if (!empty($report['orphan']))
                            <div><strong>Orfani:</strong> {{ implode(', ', $report['orphan']) }}</div>
                        @endif
                        @if (!empty($report['unsigned_match']))
                            <div><strong>Non firmati:</strong> {{ implode(', ', $report['unsigned_match']) }}</div>
                        @endif
                        @if (!empty($report['errors']))
                            <div><strong>Errori:</strong>
                                <ul class="list-disc list-inside ml-2">
                                    @foreach ($report['errors'] as $code => $msg)
                                        <li>{{ $code }}: {{ $msg }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </details>
            @endif
        </div>
    @endif

    {{-- Header + counter --}}
    <div>
        <h1 class="text-2xl font-bold" style="color:#1A1F1F;">Firma Certificati</h1>
        <p class="text-sm" style="color:#4A5252;">
            Solo il legale rappresentante può accedere a questa pagina.
            <strong>{{ $pending->count() }}</strong> {{ $pending->count() === 1 ? 'certificato' : 'certificati' }} in attesa di firma.
        </p>
    </div>

    {{-- Sezione Batch --}}
    <div class="rounded-lg bg-white p-5 space-y-4" style="border:1px solid #C8D0D0;">
        <h2 class="text-base font-bold" style="color:#1A1F1F;">Firma in batch (consigliato)</h2>
        <p class="text-sm" style="color:#4A5252;">
            Scarica tutti i certificati pending in un singolo ZIP, firmali in massa nel tuo
            software di firma qualificata (Aruba Firma Massiva, Namirial Sign, ecc.) con un solo OTP,
            poi ricarica lo ZIP firmato. Il sistema riconoscerà automaticamente i singoli certificati
            dal nome dei file (formato <code style="background:#F5F7F7; padding:1px 5px; border-radius:3px;">{codice}.pdf</code>).
        </p>

        <div class="flex flex-wrap gap-3 items-start">
            <a href="{{ route('admin.certificates.signatures.batch.download') }}"
               class="px-4 py-2 rounded-lg text-sm font-medium text-white"
               style="background:{{ $pending->isEmpty() ? '#8A9696' : '#55B1AE' }};"
               @if($pending->isEmpty()) onclick="event.preventDefault(); alert('Nessun certificato in attesa di firma.');" @endif>
                &#11015; Scarica ZIP pending ({{ $pending->count() }})
            </a>

            <form method="POST"
                  action="{{ route('admin.certificates.signatures.batch.upload') }}"
                  enctype="multipart/form-data"
                  class="flex flex-wrap gap-2 items-center">
                @csrf
                <input type="file" name="signed_zip" accept=".zip,application/zip"
                       required
                       class="text-sm rounded-lg px-3 py-2"
                       style="background:white; border:1px solid #C8D0D0;">
                <button type="submit"
                        class="px-4 py-2 rounded-lg text-sm font-medium text-white"
                        style="background:#E28A53;">
                    &#11014; Carica ZIP firmato
                </button>
            </form>
        </div>
    </div>

    {{-- Tabella Pending --}}
    <div class="rounded-lg bg-white overflow-hidden" style="border:1px solid #C8D0D0;">
        <div class="px-5 py-4" style="border-bottom:1px solid #C8D0D0;">
            <h2 class="text-base font-bold" style="color:#1A1F1F;">
                Certificati in attesa di firma ({{ $pending->count() }})
            </h2>
        </div>

        @if ($pending->isEmpty())
            <div class="p-8 text-center text-sm" style="color:#8A9696;">
                Nessun certificato in attesa di firma. &#127881;
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead style="background:#F5F7F7; color:#4A5252;">
                        <tr>
                            <th class="text-left px-4 py-3 font-semibold">Data</th>
                            <th class="text-left px-4 py-3 font-semibold">Codice</th>
                            <th class="text-left px-4 py-3 font-semibold">Studente</th>
                            <th class="text-left px-4 py-3 font-semibold">Corso</th>
                            <th class="text-left px-4 py-3 font-semibold">Voto</th>
                            <th class="text-right px-4 py-3 font-semibold">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pending as $cert)
                            <tr style="border-top:1px solid #ECEFEF;">
                                <td class="px-4 py-3 text-xs" style="color:#4A5252;">
                                    {{ $cert->issued_at->format('d/m/Y') }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">
                                    {{ $cert->code }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ $cert->student->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-xs" style="color:#4A5252;">
                                    {{ $cert->course->name ?? $cert->certification_name }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-block px-2 py-0.5 rounded text-xs font-medium"
                                          style="background:#E8F5F5; color:#3A8C89;">
                                        {{ $cert->score }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex gap-2 items-center flex-wrap justify-end">
                                        <a href="{{ route('admin.certificates.signatures.download', $cert) }}"
                                           class="text-xs px-2 py-1 rounded"
                                           style="color:#3A8C89; border:1px solid #B5DDDC;">
                                            &#11015; Scarica
                                        </a>

                                        <form method="POST"
                                              action="{{ route('admin.certificates.signatures.upload', $cert) }}"
                                              enctype="multipart/form-data"
                                              class="inline-flex gap-1 items-center">
                                            @csrf
                                            <input type="file" name="signed_pdf" accept=".pdf,application/pdf"
                                                   required
                                                   class="text-xs"
                                                   style="max-width:140px;">
                                            <button type="submit"
                                                    class="text-xs px-2 py-1 rounded text-white"
                                                    style="background:#E28A53;">
                                                Carica
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Tabella Recently Signed --}}
    @if ($recentlySigned->isNotEmpty())
        <div class="rounded-lg bg-white overflow-hidden" style="border:1px solid #C8D0D0;">
            <div class="px-5 py-4" style="border-bottom:1px solid #C8D0D0;">
                <h2 class="text-base font-bold" style="color:#1A1F1F;">
                    Firmati di recente (ultimi {{ $recentlySigned->count() }})
                </h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead style="background:#F5F7F7; color:#4A5252;">
                        <tr>
                            <th class="text-left px-4 py-3 font-semibold">Firmato il</th>
                            <th class="text-left px-4 py-3 font-semibold">Codice</th>
                            <th class="text-left px-4 py-3 font-semibold">Studente</th>
                            <th class="text-left px-4 py-3 font-semibold">Corso</th>
                            <th class="text-left px-4 py-3 font-semibold">Firmato da</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentlySigned as $cert)
                            <tr style="border-top:1px solid #ECEFEF;">
                                <td class="px-4 py-3 text-xs" style="color:#4A5252;">
                                    {{ $cert->signed_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">{{ $cert->code }}</td>
                                <td class="px-4 py-3">{{ $cert->student->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs" style="color:#4A5252;">
                                    {{ $cert->course->name ?? $cert->certification_name }}
                                </td>
                                <td class="px-4 py-3 text-xs" style="color:#4A5252;">
                                    {{ $cert->signed_by ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>
@endsection
