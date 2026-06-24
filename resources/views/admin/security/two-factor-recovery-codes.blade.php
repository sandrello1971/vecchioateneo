@extends('layouts.admin')
@section('title', isset($regenerated) ? 'Nuovi recovery codes' : 'Recovery codes 2FA')
@section('content')

<div style="max-width:700px;">
    <h2 style="font-size:1.25rem; font-weight:700; color:#1A1F1F; margin-bottom:8px;">
        {{ isset($regenerated) ? 'Nuovi recovery codes' : 'Recovery codes — Step 2 di 2' }}
    </h2>

    <div style="background:#FFF8F1; border-left:4px solid #E28A53; padding:14px 18px; border-radius:6px; margin-bottom:20px; color:#7A5230; font-size:0.875rem; line-height:1.55;">
        <p style="font-weight:700; margin-bottom:6px;">⚠ Salva questi codici in un posto sicuro ORA.</p>
        <p>Non li vedrai più. Servono se perdi accesso alla tua app authenticator. Ogni codice può essere usato <strong>una sola volta</strong>.</p>
    </div>

    <div style="background:white; border-radius:10px; padding:24px;">
        <ul style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin:0; padding:0; list-style:none;">
            @foreach($recoveryCodes as $code)
            <li style="background:#FAFBFB; padding:12px 16px; border-radius:6px; font-family:monospace; font-size:1rem; text-align:center; color:#1A1F1F; border:1px solid #F5F7F7;">{{ $code }}</li>
            @endforeach
        </ul>

        <div style="margin-top:20px; display:flex; gap:10px; align-items:center;">
            <button type="button" onclick="copyToClipboard()" style="padding:9px 16px; background:white; border:1px solid #C8D0D0; color:#4A5252; border-radius:8px; font-size:0.85rem; font-weight:600; cursor:pointer;">
                📋 Copia tutti negli appunti
            </button>
            <a href="{{ route('admin.security.2fa.show') }}" style="padding:9px 20px; background:#55B1AE; color:white; border-radius:8px; font-size:0.85rem; font-weight:700; text-decoration:none;">
                Ho salvato i codici
            </a>
        </div>
    </div>
</div>

<script>
function copyToClipboard() {
    const codes = @json($recoveryCodes).join('\n');
    navigator.clipboard.writeText(codes).then(() => alert('Recovery codes copiati negli appunti.'));
}
</script>

@endsection
