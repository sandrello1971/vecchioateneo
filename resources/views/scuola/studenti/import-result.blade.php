@extends('layouts.scuola')
@section('title', 'Import completato')
@section('breadcrumb', 'Studenti / Import / Risultato')
@section('content')
<div style="max-width:860px;">
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin-bottom:6px;">Import completato</h1>

    @if(session('success'))<div style="margin-bottom:16px; padding:12px 16px; background:#E8F5F5; border-left:4px solid #55B1AE; border-radius:6px; color:#3A8C89; font-size:0.875rem;">&#10003; {{ session('success') }}</div>@endif

    @if(count($credentials))
        <div style="background:#FBF6E2; border:1px solid #E2A653; border-radius:10px; padding:18px; margin-bottom:16px;">
            <div style="font-size:0.9rem; font-weight:700; color:#7A5B0E; margin-bottom:6px;">&#9888; Credenziali generate — mostrate UNA SOLA VOLTA</div>
            <p style="font-size:0.82rem; color:#7A5B0E; margin:0 0 12px;">
                Questi studenti non hanno email: hanno un <strong>username</strong> e una <strong>password temporanea</strong>.
                Scaricale e distribuiscile ora: per sicurezza non saranno più recuperabili (le password sono cifrate nel database).
                Al primo accesso lo studente dovrà cambiare la password.
            </p>
            <a href="{{ route('scuola.studenti.import.credentials', $batch) }}" style="display:inline-block; padding:9px 16px; background:#1A1F1F; color:#E2A653; border:1px solid #E2A653; border-radius:8px; font-size:0.85rem; font-weight:600; text-decoration:none; margin-bottom:14px;">&#11015; Scarica CSV (una tantum)</a>

            <div style="background:white; border:1px solid #E2C98A; border-radius:8px; overflow:hidden;">
                <table style="width:100%; border-collapse:collapse; font-size:0.83rem;">
                    <thead><tr style="background:#FBF3E2; text-align:left; color:#7A5B0E;">
                        <th style="padding:8px 12px;">Nome</th><th style="padding:8px 12px;">Username</th><th style="padding:8px 12px;">Password temporanea</th>
                    </tr></thead>
                    <tbody>
                    @foreach($credentials as $c)
                        <tr style="border-top:1px solid #F0E6C8;">
                            <td style="padding:8px 12px;">{{ $c['name'] }}</td>
                            <td style="padding:8px 12px; font-family:monospace;">{{ $c['username'] }}</td>
                            <td style="padding:8px 12px; font-family:monospace;">{{ $c['password'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; margin-bottom:16px; color:#4A5252; font-size:0.88rem;">
            Nessuna credenziale da consegnare a mano: gli studenti con email hanno ricevuto l'invito via email.
        </div>
    @endif

    <a href="{{ route('scuola.studenti.index') }}" style="display:inline-block; padding:10px 18px; background:#55B1AE; color:white; border-radius:8px; font-size:0.9rem; font-weight:600; text-decoration:none;">Vai all'elenco studenti</a>
</div>
@endsection
