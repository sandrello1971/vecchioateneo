@extends('layouts.scuola')
@section('title', 'Studenti')
@section('breadcrumb', 'Studenti')
@section('content')
<div style="max-width:1040px;">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:18px;">
        <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin:0;">Studenti</h1>
        <div style="display:flex; gap:8px;">
            <a href="{{ route('scuola.studenti.create') }}" style="padding:9px 16px; background:#55B1AE; color:white; border-radius:8px; font-size:0.85rem; font-weight:600; text-decoration:none;">+ Aggiungi studente</a>
            <a href="{{ route('scuola.studenti.import.create') }}" style="padding:9px 16px; background:white; color:#3A8C89; border:1px solid #55B1AE; border-radius:8px; font-size:0.85rem; font-weight:600; text-decoration:none;">&#11014; Importa CSV</a>
        </div>
    </div>

    @if(session('single_credentials'))
    <div style="margin-bottom:16px; padding:14px 16px; background:#FBF6E2; border:1px solid #E2A653; border-radius:8px; color:#7A5B0E; font-size:0.86rem;">
        <strong>&#9888; Credenziali generate — mostrate una sola volta, non recuperabili.</strong> Lo studente non ha email: consegnagli username e password temporanea (cambio obbligatorio al primo accesso).
        <table style="width:100%; border-collapse:collapse; margin-top:10px; background:white; border:1px solid #E2C98A; border-radius:6px; overflow:hidden;">
            <thead><tr style="background:#FBF3E2; text-align:left; color:#7A5B0E;"><th style="padding:7px 10px;">Nome</th><th style="padding:7px 10px;">Username</th><th style="padding:7px 10px;">Password temporanea</th></tr></thead>
            <tbody>
            @foreach(session('single_credentials') as $c)
                <tr style="border-top:1px solid #F0E6C8;"><td style="padding:7px 10px;">{{ $c['name'] }}</td><td style="padding:7px 10px; font-family:monospace;">{{ $c['username'] }}</td><td style="padding:7px 10px; font-family:monospace;">{{ $c['password'] }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
            <thead><tr style="background:#F5F7F7; text-align:left; color:#4A5252;">
                <th style="padding:10px 14px;">Nome</th><th style="padding:10px 14px;">Email / Username</th>
                <th style="padding:10px 14px;">Classe</th><th style="padding:10px 14px;">Nascita</th><th style="padding:10px 14px;">Stato</th>
                <th style="padding:10px 14px; text-align:right;">Azioni</th>
            </tr></thead>
            <tbody>
            @forelse($students as $s)
                <tr style="border-top:1px solid #F0F2F2;">
                    <td style="padding:10px 14px; color:#1A1F1F;">{{ $s->name }}</td>
                    <td style="padding:10px 14px; color:#4A5252;">{{ $s->email ?? $s->username }}</td>
                    <td style="padding:10px 14px; color:#4A5252;">{{ $s->classEnrollments->first()?->schoolClass?->name ?? '—' }}</td>
                    <td style="padding:10px 14px; color:#4A5252;">{{ $s->birth_date?->format('d/m/Y') ?? '—' }}</td>
                    <td style="padding:10px 14px;">
                        @if($s->must_change_password)<span style="font-size:0.72rem; color:#E28A53;">invito da completare</span>
                        @elseif($s->is_active)<span style="font-size:0.72rem; color:#3A8C89;">attivo</span>
                        @else<span style="font-size:0.72rem; color:#A8521F;">disattivato</span>@endif
                    </td>
                    <td style="padding:10px 14px; text-align:right;">
                        <a href="{{ route('scuola.studenti.edit', $s) }}" style="color:#3A8C89; font-weight:600; text-decoration:none;">Modifica</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="padding:18px 14px; color:#8A9696;">Nessuno studente. Importa un CSV per iniziare.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
