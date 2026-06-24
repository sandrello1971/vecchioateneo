@extends('layouts.student')
@section('title', 'Studio condiviso con il docente')
@section('breadcrumb', 'Classi / Studio condiviso')
@section('content')
<div style="max-width:760px;">
    <h1 style="font-size:1.4rem; font-weight:700; color:#1A1F1F;">&#128274; Studio condiviso con il tuo docente</h1>
    <p style="color:#4A5252; font-size:0.92rem; line-height:1.6; margin-top:12px;">
        Nelle classi, il tuo docente può vedere la tua attività di studio. Serve ad aiutarti meglio:
        capire cosa è chiaro, cosa rivedere insieme, e quali argomenti mancano nei materiali.
    </p>

    <div style="background:white; border:1px solid #C8D0D0; border-radius:10px; padding:18px; margin-top:16px;">
        <div style="font-size:0.8rem; font-weight:700; color:#4A5252; text-transform:uppercase; letter-spacing:.05em; margin-bottom:10px;">Cosa vede il docente</div>
        <ul style="margin:0 0 0 18px; color:#1A1F1F; font-size:0.9rem; line-height:1.7;">
            <li>Quali materiali pubblicati hai aperto.</li>
            <li>I risultati dei quiz (pubblicati e di autoverifica che generi tu).</li>
            <li>Le domande che fai a Minerva nella chat di classe.</li>
            <li>Gli artefatti che generi per ripassare (mappe, quiz).</li>
        </ul>
    </div>

    <div style="background:#F0F6F6; border:1px solid #C8E0E0; border-radius:10px; padding:16px 18px; margin-top:12px; font-size:0.88rem; color:#3A8C89; line-height:1.6;">
        Non è una sorveglianza: è uno strumento didattico. Sentiti libero di esercitarti, sbagliare e
        riprovare — è così che si impara. 🙂
    </div>

    <div style="margin-top:18px;">
        <a href="{{ route('student.classes.index') }}" style="color:#8A9696; font-size:0.85rem; text-decoration:none;">&larr; Torna alle mie classi</a>
    </div>
</div>
@endsection
