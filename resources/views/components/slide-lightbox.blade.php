@props(['images' => []])

{{--
  Galleria slide + lightbox (solo frontend, Alpine). Riusa i PNG dell'endpoint
  preview di S1 (stesso URL per thumbnail e immagine ingrandita: già full-res e
  in cache lato server). Usato da admin moduli e docente lezioni.
  Prop: images = array ordinato di URL preview (slide 1..N).
--}}
@once
    <style>[x-cloak]{display:none!important}</style>
@endonce

<div x-data="{ open: false, i: 0, imgs: @js(array_values($images)) }">
    {{-- ===== Galleria thumbnail (clic / Invio = apre il lightbox) ===== --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:12px;">
        <template x-for="(url, idx) in imgs" :key="idx">
            <figure @click="open = true; i = idx" @keydown.enter="open = true; i = idx"
                    tabindex="0" role="button" :aria-label="`Apri la slide ${idx + 1} a schermo intero`"
                    style="margin:0; border:1px solid #C8D0D0; border-radius:8px; overflow:hidden; background:#F4F1EA; cursor:zoom-in;">
                <img :src="url" :alt="`Slide ${idx + 1}`" loading="lazy"
                     style="display:block; width:100%; height:auto; aspect-ratio:16/9; object-fit:contain; background:#0A0A0A;">
                <figcaption x-text="`Slide ${idx + 1}`" style="padding:5px 8px; font-size:0.72rem; color:#8A9696;"></figcaption>
            </figure>
        </template>
    </div>

    {{-- ===== Lightbox ===== --}}
    <div x-show="open" x-cloak x-transition.opacity
         @keydown.escape.window="open = false"
         @keydown.arrow-left.window="if (open && i > 0) i--"
         @keydown.arrow-right.window="if (open && i < imgs.length - 1) i++"
         @click.self="open = false"
         role="dialog" aria-modal="true" aria-label="Anteprima slide a schermo intero"
         style="position:fixed; inset:0; z-index:1000; background:rgba(10,10,10,0.92); display:flex; align-items:center; justify-content:center;">

        {{-- Chiudi --}}
        <button type="button" @click="open = false" aria-label="Chiudi anteprima"
                style="position:absolute; top:14px; right:18px; background:none; border:none; color:white; font-size:2.1rem; line-height:1; cursor:pointer;">&times;</button>

        {{-- Precedente (nascosto sulla prima) --}}
        <button type="button" x-show="i > 0" @click="i--" aria-label="Slide precedente"
                style="position:absolute; left:14px; top:50%; transform:translateY(-50%); background:rgba(255,255,255,0.12); border:none; color:white; font-size:2.4rem; width:54px; height:54px; border-radius:50%; cursor:pointer;">&lsaquo;</button>

        {{-- Immagine ingrandita (stesso src della thumbnail) --}}
        <img :src="imgs[i]" :alt="`Slide ${i + 1}`"
             style="max-width:90vw; max-height:86vh; aspect-ratio:16/9; object-fit:contain; box-shadow:0 8px 40px rgba(0,0,0,0.5);">

        {{-- Successiva (nascosto sull'ultima) --}}
        <button type="button" x-show="i < imgs.length - 1" @click="i++" aria-label="Slide successiva"
                style="position:absolute; right:14px; top:50%; transform:translateY(-50%); background:rgba(255,255,255,0.12); border:none; color:white; font-size:2.4rem; width:54px; height:54px; border-radius:50%; cursor:pointer;">&rsaquo;</button>

        {{-- Indicatore N / Totale --}}
        <div x-text="`Slide ${i + 1} / ${imgs.length}`"
             style="position:absolute; bottom:16px; left:50%; transform:translateX(-50%); color:white; font-size:0.85rem; background:rgba(0,0,0,0.45); padding:4px 12px; border-radius:12px;"></div>
    </div>
</div>
