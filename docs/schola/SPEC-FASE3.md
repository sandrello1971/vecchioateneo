# Officina Schola — Fase 3: le Lezioni

> Estende la fase 2 (scuole/tenancy, completa) con l'unità didattica **Lezione**.
> Gerarchia: **Materia → Argomento → Lezione → Materiali**. Una lezione è composta
> da N materiali; dal loro contenuto il sistema compone il corpo della lezione, che
> lo studente fruisce con la UX Officina (appunti per paragrafo, ricerca video,
> Minerva), più presentazioni e messaggistica di classe.
>
> **Documento AS-BUILT** (redatto a posteriori in P23): riflette ciò che è stato
> realizzato e mergiato su `main`.

---

## AS-BUILT (2026-06-09) — fase 3 COMPLETA

| Sezione / Pacchetto | Stato | Note di realizzazione |
|---|---|---|
| §1 Schema Lezioni (P18) | ✅ FATTO | `topics`, `lessons` (generation_status), `lesson_publications`, `lesson_presentations`; `lesson_id` su `teaching_documents`/`teaching_artifacts`; UI docente Argomenti/Lezioni + classificazione materiali |
| §2 Composizione corpo (P19) | ✅ FATTO | `LessonGenerationService` + `GenerateLessonJob`: fonde N fonti (testo + artefatti + segments) in markdown; editing sempre possibile; rigenera con conferma; artefatti a livello lezione (riuso `GenerateArtifactJob`) |
| §3 Pubblicazione + RAG (P20a) | ✅ FATTO | `LessonPublicationController` (cattedra/proprietà); `LessonRagIngestor` scope `class` (corpo + segments + artefatti), `metadata.lesson_id`; ritiro→purge idempotente; `RagChunker` condiviso |
| §4 Fruizione studente (P20b) | ✅ FATTO | vista Argomento→Lezione; corpo con appunti per paragrafo (note **docente** condivise + **personali** private); ricerca video con citazioni a minutaggio; **Minerva di lezione** (gate §5, `lesson_id`) |
| §4.1 Quiz autoverifica (P20c) | ✅ FATTO | `student_generated_artifacts.lesson_publication_id`; quiz privato dello studente (escluso dal cruscotto per costruzione); stesso rate-limit/feedback di P7 |
| §5 Presentazioni `.pptx` (P21) | ✅ FATTO | `LessonPresentationService` (Claude → spec slide → python-pptx) + `GenerateLessonPresentationJob`; download gated (docente proprietario / studente della classe); storage privato |
| §6 Messaggistica di classe (P22) | ✅ FATTO | mirror messaggistica corsi in tabelle dedicate: `class_conversations`/`class_messages` (thread 1:1) + `class_announcements`/`class_announcement_reads` (broadcast); notifica email alla creazione; **segreteria esclusa** |
| §7 Rendering corpo studente | ✅ FATTO | tipografia di lettura, tabelle, KaTeX, affordance note (micro-task presentazione) |
| §8 Indurimento + as-built (P23) | ✅ FATTO | vedi sotto |

---

## 5. Vincolo RAG (§5, invariato dalla fetta 1 — NON negoziabile)

La Minerva di lezione **non** cambia il gate: per lo studente di classe risponde
**solo** da chunk `documents_rag` con `scope='class'` delle sue classi attive,
filtrati su `metadata.lesson_id` quando si è nel contesto di una lezione. Se il
retrieval non supera la soglia, il modello **non viene chiamato** e la domanda
finisce in `unanswered_questions`. Nessun chunk `teacher_private`/`platform`/di
altre classi raggiunge lo studente. (Evidenza: `StudentLessonFruitionTest`.)

---

## 8. Indurimento (P23)

Chiusura di sicurezza della fase 3. Report completo: `SECURITY_AUDIT_FASE3.md`.

- **IDOR sweep** (due scuole, due classi) su lezioni, argomenti, pubblicazioni,
  presentazioni, note, quiz studente, messaggistica → tutti **403** cross-tenant.
  Nessun finding (tenuta strutturale: proprietà/cattedra/iscrizione su ogni rotta,
  nessun id da input). Evidenza consolidata in `HardeningFase3Test`.
- **XSS / UGC**: corpo lezione via `schola_markdown` (`html_input=strip`,
  `allow_unsafe_links=false`); note via `textContent`; messaggi/annunci via escape
  Blade; KaTeX `trust:false`. Test con payload.
- **Rate limit AI**: `throttle:schola-generate` (8/min/utente) su corpo lezione,
  presentazione e quiz studente, oltre ai limiti giornalieri `ScholaUsage`.
- **KaTeX self-hosted**: asset locali `public/vendor/katex` (niente CDN → privacy
  IP studente, CSP, offline). `npm audit`: 0 vulnerabilità.
- **UX**: nascosto il box "Nessun corso attivo" per studenti di scuola senza corsi;
  verificato il plus-addressing email nell'aggiunta studente.

---

## 9. Decisioni e debiti noti (fine fase 3)

1. **DPA / flusso dati legale** (da fase 2 §8.2, bloccante prima del primo
   studente reale): gli embedding sono locali (videoai), ma la **chat va a Claude**
   (Anthropic) — il DPA scuola deve esplicitare il flusso. Da validare col legale.
2. **Deploy P21 (presentazioni)**: il venv di produzione deve avere `python-pptx`
   (`pip install python-pptx`); binario configurabile via `services.pptx.python`.
   Senza, la generazione fallisce pulita (`status=failed`), nessuna rottura.
3. **Broadcasting/Echo (Reverb)**: debito tecnico incompleto (anche nei corsi).
   La messaggistica di classe (P22) usa la meccanica durevole (email + ricevute di
   lettura + conteggi non letti); i badge live arriverebbero col completamento di
   Reverb. Vedi `docs/tech-debt.md`.
4. **SubjectSeeder** ora idempotente nel deploy (`db:seed --class=SubjectSeeder`).
