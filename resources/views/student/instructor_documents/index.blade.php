@extends('layouts.student')
@section('title', 'Documenti discenti')
@section('breadcrumb', 'Documenti discenti')

@section('content')
<div style="max-width:1200px; margin:0 auto;">

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h1 style="font-size:1.5rem; margin:0;">📂 Documenti condivisi dai discenti</h1>
    </div>

    <div style="background:rgba(85,177,174,0.08); border-left:3px solid #55B1AE; padding:10px 14px;
                border-radius:6px; margin-bottom:14px; font-size:0.8rem; color:#3A8C89;">
        Visualizzi solo i documenti che i discenti hanno scelto di condividere con i docenti. Accesso in sola lettura.
    </div>

    <form method="GET"
          style="background:white; border-radius:12px; padding:14px; margin-bottom:18px;
                 display:grid; grid-template-columns:2fr 1fr auto auto; gap:10px; align-items:end;">
        <div>
            <label style="font-size:0.7rem; color:#8A9696;">Cerca</label>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
                   placeholder="titolo, descrizione o nome discente"
                   style="width:100%; padding:7px; border:1px solid #E8F5F5; border-radius:5px; font-size:0.85rem;">
        </div>
        <div>
            <label style="font-size:0.7rem; color:#8A9696;">Corso</label>
            <select name="course_id" style="width:100%; padding:7px; border:1px solid #E8F5F5; border-radius:5px; font-size:0.8rem;">
                <option value="">Tutti</option>
                @foreach($courses as $c)
                <option value="{{ $c->id }}" {{ ($filters['course_id'] ?? null) === $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" style="padding:8px 14px; background:#55B1AE; color:white; border:none; border-radius:5px; cursor:pointer; font-size:0.8rem;">Filtra</button>
        <a href="{{ route('student.instructor_documents.index') }}"
           style="padding:8px 14px; color:#8A9696; text-decoration:none; font-size:0.8rem;">Reset</a>
    </form>

    @if($documents->isEmpty())
    <div style="background:white; border-radius:12px; padding:40px; text-align:center; color:#8A9696;">
        Nessun documento condiviso corrisponde ai filtri.
    </div>
    @else
    <div style="background:white; border-radius:12px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
            <thead style="background:#F5F7F7; color:#5A6464;">
                <tr>
                    <th style="text-align:left; padding:10px 14px; font-weight:700; font-size:0.75rem;">Discente</th>
                    <th style="text-align:left; padding:10px 14px; font-weight:700; font-size:0.75rem;">Documento</th>
                    <th style="text-align:left; padding:10px 14px; font-weight:700; font-size:0.75rem;">Corso / Modulo</th>
                    <th style="text-align:left; padding:10px 14px; font-weight:700; font-size:0.75rem;">Dim.</th>
                    <th style="text-align:left; padding:10px 14px; font-weight:700; font-size:0.75rem;">Caricato</th>
                    <th style="text-align:right; padding:10px 14px; font-weight:700; font-size:0.75rem;">Azione</th>
                </tr>
            </thead>
            <tbody>
                @foreach($documents as $doc)
                <tr style="border-top:1px solid #E8F5F5;">
                    <td style="padding:12px 14px;">
                        <div style="font-weight:600; color:#1A1F1F;">{{ $doc->student->name ?? '?' }}</div>
                        <div style="font-size:0.7rem; color:#8A9696;">{{ $doc->student->email ?? '' }}</div>
                    </td>
                    <td style="padding:12px 14px;">
                        <div style="font-weight:600; color:#1A1F1F;">{{ $doc->title }}</div>
                        @if($doc->description)
                        <div style="font-size:0.75rem; color:#5A6464; margin-top:2px;">{{ \Illuminate\Support\Str::limit($doc->description, 100) }}</div>
                        @endif
                        <div style="font-size:0.7rem; color:#8A9696; margin-top:2px;">{{ strtoupper($doc->file_type ?? '') }} · {{ $doc->original_filename }}</div>
                    </td>
                    <td style="padding:12px 14px; font-size:0.78rem; color:#5A6464;">
                        @if($doc->course)
                            <div>📚 {{ $doc->course->name }}</div>
                        @else
                            <span style="color:#8A9696;">—</span>
                        @endif
                        @if($doc->module)
                            <div style="font-size:0.7rem; color:#8A9696;">📍 {{ $doc->module->title }}</div>
                        @endif
                    </td>
                    <td style="padding:12px 14px; font-size:0.78rem; color:#5A6464;">{{ $doc->human_size }}</td>
                    <td style="padding:12px 14px; font-size:0.78rem; color:#5A6464;">{{ $doc->created_at?->format('d/m/Y H:i') }}</td>
                    <td style="padding:12px 14px; text-align:right;">
                        <a href="{{ route('student.instructor_documents.download', $doc->id) }}"
                           style="padding:6px 12px; background:#55B1AE; color:white; border-radius:5px; text-decoration:none; font-size:0.75rem;">
                            ⬇ Scarica
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div style="margin-top:18px;">
        {{ $documents->links() }}
    </div>
    @endif
</div>
@endsection
