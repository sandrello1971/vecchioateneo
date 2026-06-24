<div>
@if($show)
<div id="cookie-banner"
    style="position:fixed;bottom:0;left:0;right:0;z-index:9999;padding:1rem;background:rgba(26,31,31,0.97);border-top:3px solid #55B1AE;">
    <div style="max-width:1200px;margin:0 auto;display:flex;flex-wrap:wrap;align-items:center;gap:1rem;">
        <div style="flex:1;min-width:250px;">
            <p style="color:white;font-size:0.875rem;font-weight:600;margin:0 0 4px">Questo sito utilizza i cookie</p>
            <p style="color:#8A9696;font-size:0.75rem;margin:0">
                Utilizziamo cookie tecnici necessari al funzionamento del sito.
                <a href="/cookie-policy" style="color:#55B1AE;text-decoration:underline;margin-left:4px">Cookie Policy</a>
                <a href="/privacy-policy" style="color:#55B1AE;text-decoration:underline;margin-left:8px">Privacy Policy</a>
            </p>
        </div>
        <div style="display:flex;gap:0.75rem;flex-shrink:0;">
            <button onclick="acceptCookies('necessary')"
                style="font-size:0.75rem;padding:0.5rem 1rem;border-radius:4px;border:1px solid #55B1AE;color:#55B1AE;background:transparent;cursor:pointer;">
                Solo necessari
            </button>
            <button onclick="acceptCookies('all')"
                style="font-size:0.75rem;padding:0.5rem 1rem;border-radius:4px;border:none;background:#55B1AE;color:white;font-weight:600;cursor:pointer;">
                Accetta tutti
            </button>
        </div>
    </div>
</div>
<script>
function acceptCookies(type) {
    var expires = new Date();
    expires.setFullYear(expires.getFullYear() + 1);
    document.cookie = 'atheneum_cookie_consent=' + type + ';expires=' + expires.toUTCString() + ';path=/;SameSite=Lax';
    document.getElementById('cookie-banner').style.display = 'none';
}
</script>
@endif
</div>
