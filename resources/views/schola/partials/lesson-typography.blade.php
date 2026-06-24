{{-- Tipografia condivisa del corpo lezione (P23/rendering): usata IDENTICA dalla
     vista studente (fruizione) e dall'anteprima docente. Modificare qui sola volta. --}}
@push('styles')
<style>
/* === Corpo lezione: rendering di lettura === */
.lesson-card{background:#fff; border:1px solid #C8D0D0; border-radius:12px; padding:22px 20px; margin-top:16px;}
@media(min-width:680px){ .lesson-card{padding:30px 34px;} }

.lesson-body{max-width:70ch; color:#23292B; font-size:1rem; line-height:1.75; padding-left:26px;} /* gutter sinistro per le note */
@media(min-width:680px){ .lesson-body{font-size:1.05rem;} }
.lesson-body > :first-child{margin-top:0;}

/* Gerarchia tipografica dei titoli */
.lesson-body h1,.lesson-body h2,.lesson-body h3,.lesson-body h4{font-weight:700; line-height:1.25; margin:1.6em 0 .5em;}
.lesson-body h1{font-size:1.7rem; color:#1A2B2A; letter-spacing:-.01em;}
.lesson-body h2{font-size:1.35rem; color:#2E6F6C; padding-bottom:.25em; border-bottom:2px solid #E3ECEB;}
.lesson-body h3{font-size:1.12rem; color:#3A8C89;}
.lesson-body h4{font-size:1rem; color:#4A5252; text-transform:uppercase; letter-spacing:.04em;}

/* Testo, enfasi, link */
.lesson-body p{margin:0 0 1em;}
.lesson-body strong{color:#1A1F1F; font-weight:700;}
.lesson-body em{color:#3A4A49;}
.lesson-body a{color:#2E6F6C; text-decoration:underline; text-underline-offset:2px;}
.lesson-body a:hover{color:#55B1AE;}

/* Liste */
.lesson-body ul,.lesson-body ol{margin:0 0 1em; padding-left:1.4em;}
.lesson-body li{margin:.3em 0;}
.lesson-body li::marker{color:#55B1AE;}
.lesson-body ul ul,.lesson-body ol ol,.lesson-body ul ol,.lesson-body ol ul{margin:.2em 0;}

/* Citazioni */
.lesson-body blockquote{margin:1em 0; padding:.5em 1em; border-left:4px solid #55B1AE; background:#F2F8F8; color:#3A4A49; border-radius:0 8px 8px 0;}
.lesson-body blockquote p{margin:.3em 0;}

/* Codice */
.lesson-body code{background:#EEF3F3; color:#1A4A47; padding:1px 6px; border-radius:5px; font-size:.9em; font-family:ui-monospace,SFMono-Regular,Menlo,monospace;}
.lesson-body pre{background:#1A1F1F; color:#E8EDED; padding:14px 16px; border-radius:10px; overflow-x:auto; margin:1em 0;}
.lesson-body pre code{background:none; color:inherit; padding:0;}

/* Tabelle: header marcato, righe alternate, scroll su mobile */
.lesson-body table{display:block; max-width:100%; overflow-x:auto; border-collapse:collapse; margin:1.2em 0; font-size:.95em; -webkit-overflow-scrolling:touch;}
.lesson-body thead th{background:#2E6F6C; color:#fff; font-weight:700; text-align:left; white-space:nowrap;}
.lesson-body th,.lesson-body td{border:1px solid #D6DEDE; padding:8px 12px; vertical-align:top;}
.lesson-body tbody tr:nth-child(even){background:#F4F8F8;}

/* Separatori, immagini, formule in blocco */
.lesson-body hr{border:0; border-top:1px solid #E3ECEB; margin:1.8em 0;}
.lesson-body img{max-width:100%; height:auto; border-radius:8px;}
.lesson-body .katex-display{overflow-x:auto; overflow-y:hidden; padding:.3em 0;}

/* === Appunti per paragrafo === */
.lesson-body [data-note-anchor]{position:relative;}
.note-tab{position:absolute; left:-26px; top:.15em; width:22px; height:22px; display:flex; align-items:center; justify-content:center;
  border:none; border-radius:50%; background:transparent; color:#B7C7C6; cursor:pointer; font-size:.82rem; line-height:1; opacity:.6; transition:all .12s;}
.lesson-body [data-note-anchor]:hover .note-tab{opacity:1; color:#3A8C89; background:#EAF3F2;}
.note-tab:hover{background:#55B1AE!important; color:#fff!important; opacity:1;}
.note-tab.has-note{opacity:1; color:#E28A53;}

/* Nota PERSONALE dello studente (privata) */
.note-inline{background:#FFF7EF; border-left:3px solid #E28A53; padding:8px 12px; margin:8px 0; font-size:.9rem; color:#7A4A28; border-radius:0 8px 8px 0;}
/* Nota del DOCENTE (condivisa, didattica) */
.note-teacher{background:#EAF4F3; border-left:3px solid #2E6F6C; padding:8px 12px; margin:8px 0; font-size:.92rem; color:#1A2B2A; border-radius:0 8px 8px 0;}
.note-teacher-label{display:block; font-size:.68rem; font-weight:700; color:#2E6F6C; text-transform:uppercase; letter-spacing:.05em; margin-bottom:3px;}

@media(max-width:560px){
  .lesson-body{padding-left:22px;}
  .note-tab{left:-22px;}
  .lesson-body h1{font-size:1.45rem;} .lesson-body h2{font-size:1.2rem;} .lesson-body h3{font-size:1.05rem;}
}
</style>
@endpush
