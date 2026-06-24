@extends('layouts.app')
@section('title', 'Formazione AI per PMI — Conformi EU AI Act')
@section('description', 'Portfolio formativo AI per PMI: CONSILIUM strategia, INITIUM operativita, STRUCTURA second brain, AI AGENTS governance. Certificazioni conformi EU AI Act Art. 4.')

@section('content')
<!-- HERO -->
<section style="background:linear-gradient(135deg,#E8F5F5 0%,white 50%);position:relative;overflow:hidden;padding-top:5rem;padding-bottom:5rem;" class="px-4">
    <div style="position:absolute;inset:0;background-image:url('/images/atheneum_new.png');background-size:contain;background-position:center right;background-repeat:no-repeat;opacity:0.35;z-index:0;" aria-hidden="true"></div>
    <div class="max-w-4xl mx-auto text-center" style="position:relative;z-index:1;">
        <span class="badge-orange mb-4 inline-block">4 certificazioni &middot; ~60 ore &middot; Conformi EU AI Act</span>
        <p class="text-sm mb-3" style="color:#55B1AE;font-weight:600;letter-spacing:0.05em;text-transform:uppercase">
            Atheneum e la parte formativa dell'umanesimo digitale di Noscite
        </p>
        <h1 class="text-4xl md:text-6xl font-bold mt-4 mb-4" style="color:#1A1F1F">
            Atheneum <span style="color:#55B1AE">Noscite</span>
        </h1>
        <p class="text-base mb-4" style="color:#4A5252">Per imprenditori, manager e team operativi che vogliono usare l'AI ogni giorno in azienda, senza improvvisare.</p>
        <p class="text-xl font-semibold mb-3" style="color:#1A1F1F">Formazione AI per le PMI che vogliono crescere davvero.</p>
        <p class="text-base mb-8 max-w-2xl mx-auto" style="color:#4A5252">La conoscenza che non diventa competenza operativa non cambia nulla. Per questo la formazione Noscite non trasmette nozioni: costruisce competenza. I percorsi sono modulari, interattivi e fondati su casi reali.</p>
        <div style="display:flex; flex-wrap:wrap; gap:12px; justify-content:center; margin-top:16px; margin-bottom:24px;">
            <div style="display:flex; align-items:center; gap:6px; padding:8px 16px; background:rgba(85,177,174,0.1); border:1px solid rgba(85,177,174,0.3); border-radius:20px;">
                <span>👔</span>
                <span style="font-size:0.85rem; color:#3A8C89; font-weight:600;">Imprenditori e dirigenti PMI</span>
            </div>
            <div style="display:flex; align-items:center; gap:6px; padding:8px 16px; background:rgba(85,177,174,0.1); border:1px solid rgba(85,177,174,0.3); border-radius:20px;">
                <span>👥</span>
                <span style="font-size:0.85rem; color:#3A8C89; font-weight:600;">Team aziendali e manager</span>
            </div>
            <div style="display:flex; align-items:center; gap:6px; padding:6px 14px;
                 background:rgba(85,177,174,0.1); border:1px solid rgba(85,177,174,0.3);
                 border-radius:20px;">
                <span>🎓</span>
                <span style="font-size:0.8rem; color:#3A8C89; font-weight:600;">Studenti</span>
            </div>
            <div style="display:flex; align-items:center; gap:6px; padding:8px 16px; background:rgba(226,138,83,0.1); border:1px solid rgba(226,138,83,0.3); border-radius:20px;">
                <span>🚀</span>
                <span style="font-size:0.85rem; color:#c97a45; font-weight:600;">Nessun prerequisito tecnico</span>
            </div>
        </div>
        <div class="flex gap-4 justify-center flex-wrap">
            <a href="#corsi" class="btn-primary">Scopri i corsi</a>
            <a href="/contatti" class="btn-outline">Contattaci</a>
        </div>

        <div style="margin-top:24px; display:inline-flex; align-items:center; gap:16px;
             padding:16px 28px; background:linear-gradient(135deg,#1A1F1F,#252B2B);
             border-radius:16px; border:1px solid rgba(85,177,174,0.3);">
            <div style="text-align:left;">
                <div style="color:#55B1AE; font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:4px;">
                    ✦ Prova subito
                </div>
                <div style="color:white; font-weight:700; font-size:1rem; margin-bottom:2px;">
                    Accedi alla Demo gratuita
                </div>
                <div style="color:#8A9696; font-size:0.75rem;">
                    Esplora la piattaforma senza registrazione
                </div>
            </div>
            <a href="/learn/demo"
               style="padding:12px 24px; background:#E28A53; color:white; border-radius:10px;
                      font-size:0.9rem; font-weight:700; text-decoration:none; white-space:nowrap;
                      flex-shrink:0;">
                Entra nella Demo →
            </a>
        </div>
    </div>
</section>

<!-- PERCORSO AI -->
<section id="percorso-ai" style="padding:60px 20px; background:linear-gradient(135deg, #F7F9FC 0%, #E8F5F5 100%);">
    <div style="max-width:1200px; margin:0 auto;">

        <div style="text-align:center; margin-bottom:40px;">
            <div style="display:inline-block; padding:6px 16px; background:rgba(226,138,83,0.15);
                        color:#D87840; border-radius:20px; font-size:0.75rem; font-weight:700;
                        letter-spacing:1px; margin-bottom:14px;">
                FORMAZIONE AI PER PMI ITALIANE
            </div>
            <h2 style="font-size:2rem; color:#1A1F1F; margin:0 0 12px; font-weight:700; line-height:1.2;">
                Il Percorso AI di Noscite
            </h2>
            <p style="font-size:1rem; color:#5A6464; max-width:600px; margin:0 auto; line-height:1.5;">
                Tre corsi, tre domande: <em>perché farlo</em>, <em>cosa fare</em>, <em>come farlo</em>.
            </p>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:20px; margin-bottom:40px;">

            {{-- PRIMUS --}}
            <a href="{{ url('/primus') }}" style="text-decoration:none; color:inherit;">
                <div style="background:white; border:2px solid #E8F5F5; border-radius:16px;
                            padding:28px 22px; transition:all 0.2s; height:100%;
                            display:flex; flex-direction:column;"
                     onmouseover="this.style.borderColor='#E28A53'; this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 20px rgba(226,138,83,0.15)'"
                     onmouseout="this.style.borderColor='#E8F5F5'; this.style.transform=''; this.style.boxShadow=''">

                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                        <div style="font-size:2.5rem; font-weight:900; color:#E28A53; line-height:1;">01</div>
                        <div style="font-size:0.7rem; color:#8A9696; font-style:italic;">perché farlo</div>
                    </div>

                    <h3 style="font-size:1.4rem; color:#1A1F1F; margin:0 0 8px; font-weight:700; letter-spacing:1px;">PRIMUS</h3>
                    <p style="font-size:0.85rem; color:#5A6464; margin:0 0 4px; font-style:italic;">
                        Il risveglio consapevole
                    </p>
                    <p style="font-size:0.9rem; color:#D87840; font-weight:700; margin:0 0 16px;">
                        4 ore · Workshop esperienziale
                    </p>

                    <div style="flex:1;">
                        <p style="font-size:0.85rem; color:#5A6464; line-height:1.5; margin:0 0 16px;">
                            Scopri quanto tempo e quanti soldi oggi si perdono senza l'AI, e dove può fare davvero la differenza.
                        </p>
                    </div>

                    <div style="font-size:0.85rem; color:#3A8C89; font-weight:600;">
                        Scopri il corso →
                    </div>
                </div>
            </a>

            {{-- CONSILIUM --}}
            <a href="{{ url('/consilium') }}" style="text-decoration:none; color:inherit;">
                <div style="background:white; border:2px solid #E8F5F5; border-radius:16px;
                            padding:28px 22px; transition:all 0.2s; height:100%;
                            display:flex; flex-direction:column;"
                     onmouseover="this.style.borderColor='#E28A53'; this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 20px rgba(226,138,83,0.15)'"
                     onmouseout="this.style.borderColor='#E8F5F5'; this.style.transform=''; this.style.boxShadow=''">

                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                        <div style="font-size:2.5rem; font-weight:900; color:#E28A53; line-height:1;">02</div>
                        <div style="font-size:0.7rem; color:#8A9696; font-style:italic;">cosa fare</div>
                    </div>

                    <h3 style="font-size:1.4rem; color:#1A1F1F; margin:0 0 8px; font-weight:700; letter-spacing:1px;">CONSILIUM</h3>
                    <p style="font-size:0.85rem; color:#5A6464; margin:0 0 4px; font-style:italic;">
                        La bussola strategica
                    </p>
                    <p style="font-size:0.9rem; color:#D87840; font-weight:700; margin:0 0 16px;">
                        7 ore · Laboratorio direzionale
                    </p>

                    <div style="flex:1;">
                        <p style="font-size:0.85rem; color:#5A6464; line-height:1.5; margin:0 0 16px;">
                            La direzione decide dove usare l'AI. Esce con tre progetti scelti, un piano a 90 giorni e regole d'uso.
                        </p>
                    </div>

                    <div style="font-size:0.85rem; color:#3A8C89; font-weight:600;">
                        Scopri il corso →
                    </div>
                </div>
            </a>

            {{-- INITIUM --}}
            <a href="{{ url('/initium') }}" style="text-decoration:none; color:inherit;">
                <div style="background:white; border:2px solid #E8F5F5; border-radius:16px;
                            padding:28px 22px; transition:all 0.2s; height:100%;
                            display:flex; flex-direction:column;"
                     onmouseover="this.style.borderColor='#E28A53'; this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 20px rgba(226,138,83,0.15)'"
                     onmouseout="this.style.borderColor='#E8F5F5'; this.style.transform=''; this.style.boxShadow=''">

                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                        <div style="font-size:2.5rem; font-weight:900; color:#E28A53; line-height:1;">03</div>
                        <div style="font-size:0.7rem; color:#8A9696; font-style:italic;">come farlo</div>
                    </div>

                    <h3 style="font-size:1.4rem; color:#1A1F1F; margin:0 0 8px; font-weight:700; letter-spacing:1px;">INITIUM</h3>
                    <p style="font-size:0.85rem; color:#5A6464; margin:0 0 4px; font-style:italic;">
                        Le fondamenta operative
                    </p>
                    <p style="font-size:0.9rem; color:#D87840; font-weight:700; margin:0 0 16px;">
                        20 ore + esame · Certificazione
                    </p>

                    <div style="flex:1;">
                        <p style="font-size:0.85rem; color:#5A6464; line-height:1.5; margin:0 0 16px;">
                            Come si usa davvero l'AI sul lavoro: prompt, strumenti, sicurezza. 70% pratica, 30% teoria.
                        </p>
                    </div>

                    <div style="font-size:0.85rem; color:#3A8C89; font-weight:600;">
                        Scopri il corso →
                    </div>
                </div>
            </a>
        </div>

        {{-- Trust signal EU AI Act --}}
        <div style="text-align:center; padding:20px 20px 30px; font-size:0.85rem; color:#5A6464;
                    border-top:1px solid rgba(138,150,150,0.2); max-width:800px; margin:0 auto;">
            <strong style="color:#3A8C89;">✓ Conformità EU AI Act</strong> — Reg. UE 2024/1689 Art. 4 (AI literacy).
            INITIUM è il corso di compliance primaria, CONSILIUM copre l'area direzionale, PRIMUS prepara al percorso.
        </div>

        {{-- CTA finale --}}
        <div style="text-align:center; margin-top:20px;">
            <a href="{{ url('/contatti') }}?msg={{ urlencode('Vorrei ricevere informazioni sul Percorso AI di Noscite (PRIMUS, CONSILIUM, INITIUM).') }}"
               style="display:inline-block; padding:14px 32px; background:#E28A53; color:white;
                      border-radius:10px; text-decoration:none; font-weight:700; font-size:0.95rem;
                      box-shadow:0 4px 12px rgba(226,138,83,0.25); transition:all 0.2s;"
               onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 6px 16px rgba(226,138,83,0.4)'"
               onmouseout="this.style.transform=''; this.style.boxShadow='0 4px 12px rgba(226,138,83,0.25)'">
                📩 Richiedi info sul Percorso AI
            </a>
        </div>
    </div>
</section>

<!-- 4 CORSI -->
<section id="corsi" class="py-16 px-4">
    <div class="max-w-4xl mx-auto">
        <h2 class="text-3xl font-bold mb-2 text-center" style="color:#1A1F1F">Il Portfolio Formativo</h2>
        <p class="text-center mb-10" style="color:#4A5252">Tutti i corsi sono conformi al Reg. UE 2024/1689 (EU AI Act) Art. 4 — Obbligo di AI Literacy</p>

        <!-- TABELLA COMPARATIVA -->
        <div class="overflow-x-auto mb-10">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr style="background:#E8F5F5">
                        <th class="text-left p-3 border" style="border-color:#C8D0D0;color:#1A1F1F">Servizio</th>
                        <th class="text-left p-3 border" style="border-color:#C8D0D0;color:#1A1F1F">Durata</th>
                        <th class="text-left p-3 border" style="border-color:#C8D0D0;color:#1A1F1F;display:none;">Listino</th>
                        <th class="text-left p-3 border" style="border-color:#C8D0D0;color:#D87840;display:none;">Early Access</th>
                        <th class="text-left p-3 border" style="border-color:#C8D0D0;color:#1A1F1F">Target</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="background:#F5F7F7">
                        <td class="p-3 border" style="border-color:#C8D0D0;color:#4A5252">
                            <div class="font-bold" style="color:#8A9696">PRIMUS</div>
                            <div class="text-xs mt-1" style="color:#8A9696">Prima di tutto il perché &mdash; evento di consapevolezza AI</div>
                        </td>
                        <td class="p-3 border" style="border-color:#C8D0D0;color:#4A5252">4h &middot; mezza giornata</td>
                        <td class="p-3 border" style="border-color:#C8D0D0;color:#4A5252;display:none;">&euro; 2.500</td>
                        <td class="p-3 border font-bold" style="border-color:#C8D0D0;color:#D87840;background:rgba(226,138,83,0.05);display:none;">&euro; 1.500</td>
                        <td class="p-3 border" style="border-color:#C8D0D0;color:#4A5252">Imprenditori e dirigenti senza esperienza AI pregressa</td>
                    </tr>
                    <tr>
                        <td class="p-3 border" style="border-color:#C8D0D0;color:#4A5252">
                            <div class="font-bold" style="color:#55B1AE">CONSILIUM</div>
                            <div class="text-xs mt-1" style="color:#8A9696">Workshop direzionale o consulenza strategica facilitata</div>
                        </td>
                        <td class="p-3 border" style="border-color:#C8D0D0;color:#4A5252">7h &middot; 1 giornata</td>
                        <td class="p-3 border" style="border-color:#C8D0D0;color:#4A5252;display:none;">&euro; 5.000</td>
                        <td class="p-3 border font-bold" style="border-color:#C8D0D0;color:#D87840;background:rgba(226,138,83,0.05);display:none;">&euro; 2.500</td>
                        <td class="p-3 border" style="border-color:#C8D0D0;color:#4A5252">Direzione, board, comitato innovazione</td>
                    </tr>
                    <tr style="background:#F5F7F7">
                        <td class="p-3 border" style="border-color:#C8D0D0;color:#4A5252">
                            <div class="font-bold" style="color:#55B1AE">INITIUM</div>
                            <div class="text-xs mt-1" style="color:#8A9696">Fondamenta AI Operativa con certificazione</div>
                        </td>
                        <td class="p-3 border" style="border-color:#C8D0D0;color:#4A5252">20h + 3h esame</td>
                        <td class="p-3 border" style="border-color:#C8D0D0;color:#4A5252;display:none;">&euro; 7.500</td>
                        <td class="p-3 border font-bold" style="border-color:#C8D0D0;color:#D87840;background:rgba(226,138,83,0.05);display:none;">&euro; 4.800</td>
                        <td class="p-3 border" style="border-color:#C8D0D0;color:#4A5252">Personale che usa strumenti AI</td>
                    </tr>
                    <tr>
                        <td class="p-3 border" style="border-color:#C8D0D0;color:#4A5252">
                            <div class="font-bold" style="color:#55B1AE">STRUCTURA</div>
                            <div class="text-xs mt-1" style="color:#8A9696">Second Brain Aziendale &mdash; vault Obsidian incluso</div>
                        </td>
                        <td class="p-3 border" style="border-color:#C8D0D0;color:#4A5252">24h &middot; 6 moduli</td>
                        <td class="p-3 border" style="border-color:#C8D0D0;color:#4A5252;display:none;">&euro; 8.500</td>
                        <td class="p-3 border font-bold" style="border-color:#C8D0D0;color:#D87840;background:rgba(226,138,83,0.05);display:none;">&euro; 5.400</td>
                        <td class="p-3 border" style="border-color:#C8D0D0;color:#4A5252">Knowledge worker, Project Manager</td>
                    </tr>
                    <tr style="background:#F5F7F7">
                        <td class="p-3 border" style="border-color:#C8D0D0;color:#4A5252">
                            <div class="font-bold" style="color:#55B1AE">AGENTI AI E MCP</div>
                            <div class="text-xs mt-1" style="color:#8A9696">Agenti AI in azienda, governance HITL</div>
                        </td>
                        <td class="p-3 border" style="border-color:#C8D0D0;color:#4A5252">~9h asincrono + workshop</td>
                        <td class="p-3 border" style="border-color:#C8D0D0;color:#4A5252;display:none;">&euro; 4.900</td>
                        <td class="p-3 border font-bold" style="border-color:#C8D0D0;color:#D87840;background:rgba(226,138,83,0.05);display:none;">&euro; 3.200</td>
                        <td class="p-3 border" style="border-color:#C8D0D0;color:#4A5252">PM, responsabili innovazione, IT</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- DIAGRAMMA PERCORSO -->
        <div class="flex flex-wrap items-center justify-center gap-2 mb-10 text-sm">
            <div class="px-3 py-2 rounded text-center" style="background:#F5F7F7;border:1px dashed #C8D0D0;min-width:120px">
                <div class="font-bold" style="color:#8A9696">PRIMUS</div>
                <div style="color:#8A9696;font-size:0.7rem">Propedeutico</div>
            </div>
            <div style="color:#C8D0D0;font-size:1.2rem">&rarr;</div>
            <div class="px-3 py-2 rounded text-center" style="background:#E8F5F5;min-width:120px">
                <div class="font-bold" style="color:#55B1AE">CONSILIUM</div>
                <div style="color:#8A9696;font-size:0.7rem">Visione strategica</div>
            </div>
            <div style="color:#C8D0D0;font-size:1.2rem">&rarr;</div>
            <div class="px-3 py-2 rounded text-center" style="background:#E8F5F5;min-width:120px">
                <div class="font-bold" style="color:#55B1AE">INITIUM</div>
                <div style="color:#8A9696;font-size:0.7rem">Uso quotidiano AI</div>
            </div>
            <div style="color:#C8D0D0;font-size:1.2rem">&rarr;</div>
            <div class="px-3 py-2 rounded text-center" style="background:#E8F5F5;min-width:120px">
                <div class="font-bold" style="color:#55B1AE">STRUCTURA</div>
                <div style="color:#8A9696;font-size:0.7rem">Infrastruttura conoscenza</div>
            </div>
            <div style="color:#C8D0D0;font-size:1.2rem">&rarr;</div>
            <div class="px-3 py-2 rounded text-center" style="background:#E8F5F5;min-width:120px">
                <div class="font-bold" style="color:#55B1AE">AI AGENTS</div>
                <div style="color:#8A9696;font-size:0.7rem">Automazione avanzata</div>
            </div>
        </div>

        <div class="flex flex-col gap-8">
            <!-- PRIMUS -->
            <div class="corso-card" style="border:2px dashed #C8D0D0;background:#F5F7F7">
                <div class="flex flex-wrap gap-2 mb-3">
                    <span class="badge-teal" style="background:#F5F7F7;color:#8A9696;border:1px solid #C8D0D0">Propedeutico &middot; Nessun prerequisito</span>
                    <span style="background:#F5F7F7;color:#8A9696;padding:0.25rem 0.75rem;border-radius:9999px;font-size:0.75rem;font-weight:700">4 ore &middot; mezza giornata</span>
                    <span style="background:#F5F7F7;color:#8A9696;padding:0.25rem 0.75rem;border-radius:9999px;font-size:0.75rem;font-weight:700">Attestato di partecipazione</span>
                </div>
                <h3 class="text-2xl font-bold mb-2" style="color:#1A1F1F">PRIMUS — Prima di tutto il perche</h3>
                <p class="mb-1" style="color:#4A5252">Il punto zero del percorso Noscite. Per imprenditori e dirigenti che vogliono capire se e perche l'AI riguarda davvero la loro PMI, prima di investire in formazione.</p>
                <p class="text-sm mb-4 italic" style="color:#8A9696">Output: Mappa di Maturita Digitale personalizzata + percorso Noscite consigliato.</p>
                <div class="grid md:grid-cols-2 gap-2 mb-4">
                    <div class="text-sm p-2 rounded" style="background:white"><strong>M1</strong> Il mondo che non aspetta <span style="color:#8A9696">(45')</span></div>
                    <div class="text-sm p-2 rounded" style="background:white"><strong>M2</strong> Il prezzo dell'invisibilita <span style="color:#8A9696">(60')</span></div>
                    <div class="text-sm p-2 rounded" style="background:white"><strong>M3</strong> La tua azienda nell'AI <span style="color:#8A9696">(60')</span></div>
                    <div class="text-sm p-2 rounded" style="background:white"><strong>M4</strong> La tua mappa e il tuo percorso <span style="color:#8A9696">(35')</span></div>
                </div>
                <a href="/contatti" class="btn-primary" style="background:#8A9696">Richiedi informazioni su PRIMUS</a>
            </div>

            <!-- CONSILIUM -->
            <div class="corso-card">
                <div class="flex flex-wrap gap-2 mb-3">
                    <span class="badge-teal">Per Board &middot; Direzione &middot; Imprenditori</span>
                    <span class="badge-orange">7 ore &middot; 1 giornata</span>
                    <span class="badge-teal">Certified AI Strategist</span>
                </div>
                <h3 class="text-2xl font-bold mb-2" style="color:#1A1F1F">CONSILIUM — Strategia AI per PMI</h3>
                <p class="mb-4" style="color:#4A5252">Il laboratorio direzionale Noscite. Dedicato a imprenditori e vertici aziendali che vogliono costruire una visione strategica sull'AI: non seguire la moda, ma guidare una trasformazione con metodo e consapevolezza. Formato 25% teoria, 75% lavoro laboratoriale su canvas. Ogni modulo produce un deliverable operativo.</p>
                <div class="grid md:grid-cols-2 gap-2 mb-4">
                    <div class="text-sm p-2 rounded" style="background:#E8F5F5"><strong>M1</strong> Scenario AI per PMI — opportunita, rischi e casi d'uso <span style="color:#8A9696">(1h 30')</span></div>
                    <div class="text-sm p-2 rounded" style="background:#E8F5F5"><strong>M2</strong> Mappatura processi e identificazione casi d'uso AI <span style="color:#8A9696">(2h)</span></div>
                    <div class="text-sm p-2 rounded" style="background:#E8F5F5"><strong>M3</strong> Selezione 3 progetti prioritari e definizione owner <span style="color:#8A9696">(1h 30')</span></div>
                    <div class="text-sm p-2 rounded" style="background:#E8F5F5"><strong>M4</strong> AI Usage Policy essenziale e Roadmap 90 giorni <span style="color:#8A9696">(2h)</span></div>
                </div>
                <a href="/contatti" class="btn-orange">Richiedi informazioni</a>
            </div>

            <!-- INITIUM -->
            <div class="corso-card">
                <div class="flex flex-wrap gap-2 mb-3">
                    <span class="badge-teal">Per Manager &middot; Professionisti &middot; Team operativi</span>
                    <span class="badge-orange">20h + 3h esame</span>
                    <span class="badge-teal">Certified AI Productivity User</span>
                </div>
                <h3 class="text-2xl font-bold mb-2" style="color:#1A1F1F">INITIUM — Fondamenta AI Operativa</h3>
                <p class="mb-4" style="color:#4A5252">Il punto di partenza per chi vuole capire davvero l'AI generativa. Corso di compliance primaria AI Act: soddisfa i requisiti dell'Art. 4 Reg. UE 2024/1689 per tutto il personale che usa sistemi AI. Formato 70% pratico, 30% teoria.</p>
                <div class="grid md:grid-cols-2 gap-2 mb-4">
                    <div class="text-sm p-2 rounded" style="background:#E8F5F5"><strong>M1</strong> Capire l'AI — logica, dati e limiti <span style="color:#8A9696">(4h)</span></div>
                    <div class="text-sm p-2 rounded" style="background:#E8F5F5"><strong>M2</strong> Prompt Engineering e Perplexity in Azione <span style="color:#8A9696">(4h)</span></div>
                    <div class="text-sm p-2 rounded" style="background:#E8F5F5"><strong>M3</strong> Claude e ChatGPT — analisi, contenuti e automazioni <span style="color:#8A9696">(4h)</span></div>
                    <div class="text-sm p-2 rounded" style="background:#E8F5F5"><strong>M4</strong> Vibe Coding e Claude oltre il browser <span style="color:#8A9696">(4h)</span></div>
                    <div class="text-sm p-2 rounded" style="background:#E8F5F5"><strong>M5</strong> Second Brain, Data Governance e Private AI <span style="color:#8A9696">(4h)</span></div>
                    <div class="text-sm p-2 rounded" style="background:#fff3ec; border:1px solid #E28A53"><strong>Esame</strong> Certified AI Productivity User — soglia 70/100 <span style="color:#8A9696">(3h)</span></div>
                </div>
                <a href="/contatti" class="btn-orange">Richiedi informazioni</a>
            </div>

            <!-- STRUCTURA -->
            <div class="corso-card">
                <div class="flex flex-wrap gap-2 mb-3">
                    <span class="badge-teal">Per Manager &middot; Knowledge Worker &middot; PM</span>
                    <span class="badge-orange">24 ore &middot; 6 moduli</span>
                    <span class="badge-teal">Certified Second Brain Implementer</span>
                </div>
                <h3 class="text-2xl font-bold mb-2" style="color:#1A1F1F">STRUCTURA — Second Brain Aziendale</h3>
                <p class="mb-1" style="color:#4A5252">Percorso avanzato per implementare sistemi di knowledge management con approccio AI-driven. Produce documentazione audit-ready: vault Obsidian, Playbook di governance, roadmap 90 giorni.</p>
                <p class="text-sm mb-4 italic" style="color:#E28A53">Prerequisito consigliato: INITIUM</p>
                <div class="grid md:grid-cols-2 gap-2 mb-4">
                    <div class="text-sm p-2 rounded" style="background:#E8F5F5"><strong>M1</strong> Metodo CODE e fondamenti del Second Brain <span style="color:#8A9696">(4h)</span></div>
                    <div class="text-sm p-2 rounded" style="background:#E8F5F5"><strong>M2</strong> Setup Obsidian e Vault Aziendale <span style="color:#8A9696">(4h)</span></div>
                    <div class="text-sm p-2 rounded" style="background:#E8F5F5"><strong>M3</strong> Template e Organizzazione Avanzata <span style="color:#8A9696">(4h)</span></div>
                    <div class="text-sm p-2 rounded" style="background:#E8F5F5"><strong>M4</strong> AI e Automazioni nel Vault <span style="color:#8A9696">(4h)</span></div>
                    <div class="text-sm p-2 rounded" style="background:#E8F5F5"><strong>M5</strong> Collaborazione e Governance del Vault <span style="color:#8A9696">(4h)</span></div>
                    <div class="text-sm p-2 rounded" style="background:#E8F5F5"><strong>M6</strong> Certificazione e Piano d'Azione <span style="color:#8A9696">(4h)</span></div>
                </div>
                <a href="/contatti" class="btn-orange">Richiedi informazioni</a>
            </div>

            <!-- AI AGENTS & MCP -->
            <div class="corso-card">
                <div class="flex flex-wrap gap-2 mb-3">
                    <span class="badge-teal">Per Manager &middot; PM &middot; Responsabili Innovazione &middot; IT</span>
                    <span class="badge-orange">~9 ore &middot; L1 asincrono + L2 workshop</span>
                    <span class="badge-teal">Certified AI Agent Governance Practitioner</span>
                </div>
                <h3 class="text-2xl font-bold mb-2" style="color:#1A1F1F">AI AGENTS & MCP — Agenti AI in Azienda</h3>
                <p class="mb-1" style="color:#4A5252">L'unico corso del portfolio dedicato alla governance degli agenti AI e del protocollo MCP. Livello 1 asincrono fruibile da qualsiasi dispositivo. Livello 2 workshop in presenza su casi reali con demo live MCPHub Noscite.</p>
                <p class="text-sm mb-4 italic" style="color:#E28A53">Prerequisito: INITIUM o equivalente &middot; Max 12 partecipanti L2</p>
                <div class="grid md:grid-cols-2 gap-2 mb-4">
                    <div class="text-sm p-2 rounded" style="background:#E8F5F5"><strong>L1</strong> Asincrono ~3h: paradigma agenti, MCP, casi PMI, governance</div>
                    <div class="text-sm p-2 rounded" style="background:#E8F5F5"><strong>L2 A</strong> Fondamenta MCP e demo live MCPHub <span style="color:#8A9696">(2h)</span></div>
                    <div class="text-sm p-2 rounded" style="background:#E8F5F5"><strong>L2 B</strong> Canvas Architetto dell'Agente su caso reale <span style="color:#8A9696">(2h)</span></div>
                    <div class="text-sm p-2 rounded" style="background:#E8F5F5"><strong>L2 C+D</strong> Demo produzione + Piano d'Azione 90 giorni <span style="color:#8A9696">(2h)</span></div>
                </div>
                <a href="/contatti" class="btn-orange">Richiedi informazioni</a>
            </div>
        </div>
    </div>
</section>

<!-- CTA FINALE -->
<section style="background:#55B1AE;" class="py-16 px-4 text-center text-white">
    <h2 class="text-3xl font-bold mb-4">Tutti i percorsi sono costruiti su casi reali.</h2>
    <p class="mb-2">Conformi al Reg. UE 2024/1689 (EU AI Act) Art. 4 — Obbligo di AI Literacy</p>
    <p class="mb-8 opacity-80">Il traguardo vero e l'autonomia: team capaci di governare gli strumenti AI, non dipendenti da essi.</p>
    <a href="/contatti" style="background:white; color:#55B1AE;" class="btn-primary">Contattaci per un percorso personalizzato</a>
</section>
@endsection
