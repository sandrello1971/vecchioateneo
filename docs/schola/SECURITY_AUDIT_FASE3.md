# Schola Fase 3 — Security Audit (P23, 2026-06-09)

Indurimento e chiusura della fase 3 (Lezioni: P18–P22). Audit di TUTTA la
superficie studente-facing e UGC: lezioni/argomenti, pubblicazioni, presentazioni,
note (docente + personali), quiz auto-generato, messaggistica di classe.

Evidenza nei test: `TopicLessonTest`, `LessonGenerationTest`, `LessonPublicationTest`,
`StudentLessonFruitionTest`, `LessonStudentGenerationTest`, `LessonPresentationTest`,
`ClassMessagingTest` e — consolidato multi-scuola/XSS/rate-limit — `HardeningFase3Test`.

## Sintesi per categoria

| Categoria | Esito | Note |
|---|---|---|
| IDOR lezioni (view/edit/componi/artefatti) | ✅ PASS | `LessonController`/`LessonGenerationController` con `teacher_id === session` su ogni azione; cross-scuola **403** (`HardeningFase3Test`, `LessonGenerationTest`) |
| IDOR argomenti / classificazione materiali | ✅ PASS | proprietà su topic + materiale del docente; `TopicLessonTest` |
| IDOR pubblicazioni lezione (pubblica/ritira) | ✅ PASS | classe scuola → cattedra (`TeacherClassAccess`); classe libera → proprietà; `LessonPublicationTest` |
| Presentazioni `.pptx` (download) | ✅ PASS | docente proprietario / studente della classe pubblicata; altro docente o altra classe **403**; solo `ready`; storage privato (`LessonPresentationTest`) |
| Note del docente (per paragrafo) | ✅ PASS | scrittura solo proprietario lezione; lette da tutti gli studenti della classe (didattiche) |
| Note PERSONALI studente (privacy) | ✅ PASS | tabella `student_lesson_notes` referenziata **solo** da codice studente; il docente NON le vede su nessuna vista né nel **cruscotto** (`StudentLessonFruitionTest`, riconferma P20) |
| Quiz auto-generato studente (privato) | ✅ PASS | `lesson_publication_id` ≠ NULL → escluso per costruzione dal cruscotto (`ClassSignalsService` filtra per `artifact_publication_id`); `LessonStudentGenerationTest` |
| Messaggistica di classe (thread/annunci) | ✅ PASS | isolamento per classe; studente↔solo docenti delle sue classi active; docente↔solo sue cattedre; **segreteria esclusa** (`ClassMessagingTest`) |
| Minerva di lezione (vincolo §5) | ✅ PASS | retrieval scoped `class` + `lesson_id`; fuori-KB → niente modello + `unanswered_questions`; cross-classe **403** (`StudentLessonFruitionTest`, `HardeningFase3Test`) |
| XSS / sanitizzazione UGC | ✅ PASS | corpo lezione via `schola_markdown` (`html_input=strip`, `allow_unsafe_links=false`); note/messaggi/annunci resi con escape Blade / `textContent` |
| Rate limit generazioni AI | ✅ PASS | `throttle:schola-generate` (8/min/utente) su corpo lezione, rigenera, artefatti, presentazione (gen+rigenera), quiz studente |
| KaTeX self-hosted (privacy/CSP) | ✅ FIX | rimossa la CDN: asset locali in `public/vendor/katex` → niente IP studente verso terzi, compatibile CSP/offline |
| Plus-addressing email (aggiunta studente) | ✅ PASS | accettato server (`filter_var`/regola `email`) e client (`type=email`); account creato con l'indirizzo esatto |
| Mass assignment | ✅ PASS | create/update con array espliciti; `teacher_id`/`student_id`/binding impostati server-side |

Legenda: ✅ PASS conforme · ✅ FIX rilievo risolto in questo pacchetto.

---

## 1. IDOR sweep — due scuole, due classi

Sweep multi-scuola (A, B) e multi-classe sulle superfici **nuove** di fase 3
(l'IDOR per singola feature è già coperto nei test dedicati P18–P22; qui il taglio
cross-tenant è consolidato in `HardeningFase3Test`):

- **Docente di B su lezione di A** → `lessons.show/generate/content/presentation.generate/presentation.download/teacher-notes.save` tutti **403**.
- **Studente della classe B su lezione/presentazione/Minerva di A** → **403** (lezione non pubblicata sulla sua classe + classe non sua).
- **Messaggistica**: studente non scrive ai docenti di altre classi; docente non accede alla messaggistica di classi senza cattedra; thread non visibile ad altro studente; **segreteria** (né docente né studente attivo) esclusa → **403**.

**Causa strutturale della tenuta**: ogni rotta parametrizzata verifica la
proprietà/cattedra/iscrizione (`abort_unless`), nessun controller accetta
`teacher_id`/`student_id`/`school_id` da input, e l'accesso del docente passa
sempre da `TeacherClassAccess`. **Nessun finding IDOR** → nessun commit di fix.

## 2. XSS / sanitizzazione UGC

Tutto il contenuto utente reso a un altro utente è neutralizzato:

- **Corpo lezione** (markdown editabile dal docente, reso agli studenti via
  `{!! $bodyHtml !!}`): passa da `schola_markdown()` con `html_input='strip'`
  (rimuove `<script>`, `<img onerror>`, ogni HTML grezzo) e `allow_unsafe_links=false`
  (blocca `javascript:`/`data:` nei link). Verificato con payload in `HardeningFase3Test`.
- **Note docente/personali**: rese lato client con `textContent` (mai `innerHTML`).
- **Messaggi e annunci**: resi con `{{ }}` (escape Blade) → il payload appare come
  testo, mai come markup eseguibile.
- **KaTeX**: configurato con i default (`trust:false`, `strict`), quindi `\htmlData`/
  `\href` verso `javascript:` sono disabilitati (la vuln moderate dell'advisory
  KaTeX non è sfruttabile in questa configurazione; libreria comunque aggiornata).

**Nessun finding** → la sanitizzazione era già in essere (hardening P10), qui
estesa con test di regressione su tutte le superfici fase 3.

## 3. Rate limit generazioni AI

Tutte le rotte di generazione AI di fase 3 montano `throttle:schola-generate`
(`Limit::perMinute(8)` per `student_id|ip`, definito in `AppServiceProvider`):
corpo lezione (`componi`/`ricomponi`), artefatti di lezione, presentazione
(`presentazione`/`rigenera`), quiz studente (`genera`). Test del limite in
`HardeningFase3Test::test_ai_generation_is_rate_limited_per_user` (9ª richiesta
bloccata). I limiti **giornalieri** per studente restano gestiti da `ScholaUsage`.

## 4. Rilievi risolti (fix)

- **KaTeX self-hosted** (commit dedicato): asset locali `public/vendor/katex`,
  niente richiesta dello studente alla CDN jsdelivr — privacy (nessun IP verso
  terzi), CSP più stretta possibile, funzionamento offline.
- **`npm audit`**: 0 vulnerabilità (axios → patch; KaTeX → 0.16.47).

## 5. Rifiniture UX (collaudo)

- Box "Nessun corso attivo" nascosto per gli studenti di **scuola** senza
  iscrizioni a corsi (rumore dual-identity): mostrato solo a chi non ha né corsi
  né classi.
- **Plus-addressing** nell'aggiunta studente: verificato che `nome+tag@dominio`
  è accettato e l'account creato con l'indirizzo esatto (regression test).

## 6. Regressione

Confermata: suite completa verde. Mondo corsi/formatori/admin, fetta 1 (docenti
liberi) e fase 2 (tenancy scuola) invariati. La messaggistica di classe vive in
tabelle dedicate: `conversations`/`messages` dei corsi a zero righe Schola.
