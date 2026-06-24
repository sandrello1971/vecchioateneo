@extends('layouts.scuola')
@section('title', 'Anagrafica & branding')
@section('breadcrumb', 'Anagrafica & branding')
@section('content')
@php $hasLogo = (bool) $school->setting('logo_path'); @endphp
<div style="max-width:680px;">
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F; margin-bottom:16px;">Anagrafica & branding</h1>

    @if($errors->any())<div style="margin-bottom:14px; padding:10px 14px; background:#FDECE2; border-left:4px solid #E28A53; border-radius:6px; color:#A8521F; font-size:0.85rem;">{{ $errors->first() }}</div>@endif

    <form method="POST" action="{{ route('scuola.anagrafica.update') }}" enctype="multipart/form-data" data-async
          style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:20px;">
        @csrf @method('PATCH')

        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:12px;">Dati scuola</div>
        <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Nome *</label>
        <input type="text" name="name" value="{{ old('name', $school->name) }}" required style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; margin-bottom:14px;">

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
            <div>
                <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Tipo *</label>
                <select name="type" style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem;">
                    @foreach(['liceo'=>'Liceo','istituto_tecnico'=>'Istituto tecnico','altro'=>'Altro'] as $k=>$lab)
                        <option value="{{ $k }}" @selected(old('type', $school->type)===$k)>{{ $lab }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Città</label>
                <input type="text" name="city" value="{{ old('city', $school->city) }}" style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem;">
            </div>
        </div>

        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin:20px 0 12px;">Branding (white-label)</div>
        <p style="font-size:0.78rem; color:#8A9696; margin-bottom:12px;">Lascia vuoto per ereditare il default della piattaforma.</p>

        <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Nome istanza (mostrato nel layout)</label>
        <input type="text" name="instance_name" value="{{ old('instance_name', $school->setting('instance_name')) }}" placeholder="es. Liceo Galilei — Officina" style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; margin-bottom:14px;">

        <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Nome assistente (Minerva)</label>
        <input type="text" name="assistant_name" value="{{ old('assistant_name', $school->setting('assistant_name')) }}" placeholder="es. Minerva" style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem; margin-bottom:14px;">

        <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Logo</label>
        <div style="display:flex; align-items:center; gap:14px; margin-bottom:6px;">
            @if($hasLogo)
                <img src="{{ route('scuola.logo', $school) }}" alt="logo" style="height:40px; background:#1A1F1F; padding:6px; border-radius:6px;">
                <label style="font-size:0.8rem; color:#A8521F;"><input type="checkbox" name="remove_logo" value="1"> rimuovi logo</label>
            @else
                <span style="font-size:0.8rem; color:#8A9696;">Nessun logo: usato quello piattaforma.</span>
            @endif
        </div>
        <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp" style="font-size:0.85rem; margin-bottom:18px;">

        @php
            $curTheme  = old('base_theme', $brand?->base_theme?->value ?? 'glitch');
            $curAccent = old('accent_color', $brand?->accent_color);
            $curFont   = old('font_choice', $brand?->font_choice);
        @endphp

        <div style="font-size:0.75rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:0.05em; margin:24px 0 8px;">Tema presentazioni</div>
        <p style="font-size:0.78rem; color:#8A9696; margin-bottom:12px;">Scegli un tema base curato; accento e font sono override opzionali (vuoto = quelli del tema). Lo sfondo e il colore del testo sono fissati dal tema per garantire la leggibilità.</p>

        {{-- Scelta tema base: 4 card con swatch colori + nomi font --}}
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; margin-bottom:16px;">
            @foreach($themes as $t)
                <label class="brand-theme-card" data-value="{{ $t['value'] }}"
                       style="position:relative; cursor:pointer; border:2px solid {{ $curTheme===$t['value'] ? '#55B1AE' : '#E2E8E8' }}; border-radius:10px; padding:12px;">
                    <input type="radio" name="base_theme" value="{{ $t['value'] }}" @checked($curTheme===$t['value'])
                           style="position:absolute; opacity:0; pointer-events:none;">
                    <div style="display:flex; gap:5px; margin-bottom:8px;">
                        <span title="testo" style="width:24px; height:24px; border-radius:5px; background:#{{ $t['palette']['ink'] }};"></span>
                        <span title="sfondo" style="width:24px; height:24px; border-radius:5px; border:1px solid #D8DEDE; background:#{{ $t['palette']['background'] }};"></span>
                        <span title="accento" style="width:24px; height:24px; border-radius:5px; background:#{{ $t['palette']['accent'] }};"></span>
                    </div>
                    <div style="font-weight:700; font-size:0.85rem; color:#1A1F1F;">{{ $t['label'] }}</div>
                    <div style="font-size:0.72rem; color:#8A9696; margin-top:2px;">{{ $t['fonts']['title']['primary'] }} · {{ $t['fonts']['body']['primary'] }}</div>
                </label>
            @endforeach
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
            <div>
                <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Accento (override)</label>
                <div style="display:flex; align-items:center; gap:8px;">
                    <input type="color" id="accent_picker" value="#{{ $curAccent ?: 'A6192E' }}" style="width:42px; height:38px; border:1px solid #C8D0D0; border-radius:8px; padding:2px; cursor:pointer;">
                    <input type="text" name="accent_color" id="accent_color" value="{{ $curAccent }}" placeholder="vuoto = accento del tema" maxlength="7"
                           style="flex:1; padding:9px 12px; border:1px solid {{ $errors->has('accent_color') ? '#E28A53' : '#C8D0D0' }}; border-radius:8px; font-size:0.9rem; font-family:monospace;">
                </div>
                @error('accent_color')<div style="font-size:0.75rem; color:#A8521F; margin-top:4px;">{{ $message }}</div>@enderror
            </div>
            <div>
                <label style="display:block; font-size:0.82rem; font-weight:600; color:#4A5252; margin-bottom:4px;">Font (override)</label>
                <select name="font_choice" id="font_choice" style="width:100%; padding:9px 12px; border:1px solid #C8D0D0; border-radius:8px; font-size:0.9rem;">
                    <option value="">(font del tema)</option>
                    @foreach($fonts as $key => $pair)
                        <option value="{{ $key }}" @selected($curFont===$key)>{{ $pair['title']['primary'] }} / {{ $pair['body']['primary'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Logo: ereditato, nessun upload separato --}}
        <div style="font-size:0.78rem; color:#8A9696; margin-bottom:14px; padding:10px 12px; background:#F5F7F7; border-radius:8px;">
            @if($hasLogo)
                <img src="{{ route('scuola.logo', $school) }}" alt="logo" style="height:28px; vertical-align:middle; background:#1A1F1F; padding:4px; border-radius:5px; margin-right:8px;">
            @endif
            Lo stesso logo della scuola (sopra) sarà usato sulle slide. {{ $hasLogo ? '' : 'Nessun logo caricato: verrà usato quello della piattaforma.' }}
        </div>

        {{-- Anteprima live del tema risultante (16:9) --}}
        <div style="font-size:0.78rem; font-weight:600; color:#4A5252; margin-bottom:6px;">Anteprima</div>
        <div id="brand-preview" style="border:1px solid #C8D0D0; border-radius:10px; overflow:hidden; margin-bottom:20px;">
            <div id="bp-stage" style="aspect-ratio:16/9; padding:22px; display:flex; flex-direction:column; justify-content:center;">
                <div id="bp-title" style="font-size:1.5rem; font-weight:700; line-height:1.2;">Titolo della lezione</div>
                <div id="bp-body" style="font-size:0.92rem; margin-top:10px;">Esempio di contenuto della slide: come appariranno i testi del corpo.</div>
                <div id="bp-fonts" style="font-size:0.7rem; margin-top:16px; opacity:0.6;"></div>
            </div>
        </div>

        <div>
            <button data-busy-label="Salvo…" style="padding:10px 18px; background:#55B1AE; color:white; border:none; border-radius:8px; font-size:0.9rem; font-weight:600; cursor:pointer;">Salva</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
(function () {
    const THEMES = @json($themes);
    const FONTS  = @json($fonts);
    const byValue = Object.fromEntries(THEMES.map(t => [t.value, t]));

    const cards   = document.querySelectorAll('.brand-theme-card');
    const radios  = document.querySelectorAll('input[name="base_theme"]');
    const accentText   = document.getElementById('accent_color');
    const accentPicker = document.getElementById('accent_picker');
    const fontSel = document.getElementById('font_choice');

    const stage = document.getElementById('bp-stage');
    const elT = document.getElementById('bp-title');
    const elB = document.getElementById('bp-body');
    const elF = document.getElementById('bp-fonts');

    const isHex6 = v => /^[0-9A-Fa-f]{6}$/.test(v);
    const clean  = v => (v || '').replace(/^#/, '').trim();

    function selectedTheme() {
        const r = document.querySelector('input[name="base_theme"]:checked');
        return byValue[r ? r.value : THEMES[0].value];
    }

    function render() {
        const theme = selectedTheme();
        if (!theme) return;

        // Card selezionata evidenziata
        cards.forEach(c => c.style.borderColor = (c.dataset.value === theme.value) ? '#55B1AE' : '#E2E8E8');

        // Accento: override se hex valido, altrimenti quello del tema
        const ov = clean(accentText.value);
        const accent = isHex6(ov) ? ov : theme.palette.accent;

        // Font: override dal catalogo se scelto, altrimenti quelli del tema
        const fonts = (fontSel.value && FONTS[fontSel.value]) ? FONTS[fontSel.value] : theme.fonts;

        stage.style.background = '#' + theme.palette.background;
        elT.style.color = '#' + accent;
        elT.style.fontFamily = "'" + fonts.title.primary + "','" + fonts.title.fallback + "',serif";
        elB.style.color = '#' + theme.palette.ink;
        elB.style.fontFamily = "'" + fonts.body.primary + "','" + fonts.body.fallback + "',sans-serif";
        elF.style.color = '#' + theme.palette.ink;
        elF.textContent = 'Titoli: ' + fonts.title.primary + ' · Corpo: ' + fonts.body.primary;

        // Sincronizza il color picker con l'accento effettivo
        accentPicker.value = '#' + accent;
    }

    radios.forEach(r => r.addEventListener('change', render));
    fontSel.addEventListener('change', render);
    accentText.addEventListener('input', render);
    accentPicker.addEventListener('input', function () {
        accentText.value = clean(accentPicker.value).toUpperCase();
        render();
    });

    render();
})();
</script>
@endpush
@endsection
