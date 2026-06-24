@php($isMinor = ($audience ?? 'adult') === 'minor')
<span style="font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.06em; padding:2px 9px; border-radius:10px;
    {{ $isMinor
        ? 'background:#7B1E1E; color:#FBEDEC; border:1px solid #7B1E1E;'
        : 'background:#EEF3F3; color:#5A6666; border:1px solid #D6DEDE;' }}">
    {{ $isMinor ? '⚠ MINORI' : 'adulti' }}
</span>
