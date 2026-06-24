# Schola — Security Audit (pacchetto 10, 2026-06-08)

Audit mirato di sicurezza su tutto il modulo Schola (pacchetti 1–9), con
correzioni nello stesso branch `schola/10-hardening` (un commit per finding) e
test di regressione/evidenza in `tests/Feature/Schola/HardeningTest.php`.

## Sintesi per categoria

| Categoria | Esito | Note |
|---|---|---|
| IDOR `/docente/*` (cross-docente) | ✅ PASS | owner-check su ogni risorsa; sweep automatico |
| IDOR `/learn/classi/*` (enrollment) | ✅ PASS | solo iscrizione `active`; pending/removed/estraneo negati ovunque |
| Storage privato / path traversal | ✅ PASS | serve solo via controller, path da DB (indice/0), mai input utente |
| Enumerabilità codici invito | ✅ PASS | messaggio generico unico + `throttle:class-join` |
| Mass assignment | ✅ PASS | create/update con array espliciti; nessun `$request->all()` |
| XSS (markdown/markmap/vis) | ⚠️→✅ FIX | link `javascript:` nei markdown → sanitizzati |
| CSRF (async/PATCH/polling) | ✅ PASS | middleware `web`; mutazioni con `@csrf`/header CSRF; polling = GET |
| Rate limit endpoint AI | ⚠️→✅ FIX | mancava throttle/min sulle generazioni → aggiunto |

Legenda: ✅ PASS già conforme · ⚠️→✅ FIX vulnerabilità trovata e corretta.

---

## Finding 1 — XSS via link `javascript:` nel markdown (CORRETTO)

**Dove**: render Markdown degli artefatti (`docente/artefatti/show`,
`student/classi/artefatto`, `docente/biblioteca/show`) via `Str::markdown`.

**Problema**: l'HTML grezzo era già escapato (default), ma con
`allow_unsafe_links` di default `true` i link `[x](javascript:alert(1))` venivano
renderizzati come `<a href="javascript:alert(1)">` → XSS al click. Vettore
realistico: contenuto **AI** prompt-injectabile dai documenti sorgente, o
**editing manuale** del docente.

**Fix** (commit *fix(schola): XSS — sanitizza il markdown*): helper
`schola_markdown()` con `html_input=strip` + `allow_unsafe_links=false`, usato nei
3 punti di render. I link sicuri (`https://…`) restano; gli href pericolosi no.

**Test**: `test_artifact_markdown_is_sanitized` (no `href="javascript:`, no
`<script>` eseguibile; link sicuro presente).

**Note markmap/vis-network**: i payload mindmap (markdown markmap) e conceptmap
(JSON `{nodes,edges}`) sono renderizzati client-side; vis-network mostra le label
come **testo** (HTML non abilitato). Rischio residuo basso; il contenuto resta
AI/docente. Da rivalutare se in futuro si abilita l'HTML nelle label.

## Finding 2 — Rate limit assente sugli endpoint che dispatchano AI (CORRETTO)

**Dove**: `student.classes.artifact.generate`, `docente.artifacts.generate`,
`docente.artifacts.regenerate`, `docente.materials.store`, `docente.materials.retry`.

**Problema**: solo il tetto **giornaliero** (§8.2) sulle generazioni studente;
nessun throttle per-minuto → possibile burst di chiamate AI in un minuto.

**Fix** (commit *fix(schola): rate limit sugli endpoint AI*): rate limiter
`schola-generate` (8/min per utente|IP) su tutti gli endpoint sopra. La chat
Minerva era già coperta (`throttle:minerva-chat`, 20/min) + tetto giornaliero
chat.

**Test**: `test_generation_endpoint_is_throttled_per_minute` (oltre l'8ª richiesta
nessuna riga creata).

---

## Categorie verificate conformi (PASS)

### IDOR `/docente/*`
Tutte le rotte dietro middleware `professor` (gate `role=professor`) **e**
owner-check per risorsa: artefatti/materiali/classi/pubblicazioni/domande →
`teacher_id === session('student_id')` o ownership della classe; le risorse
annidate verificano l'appartenenza (`enrollment.school_class_id === class.id`,
`publication.school_class_id === class.id`, `question.schoolClass.teacher_id`).
Evidenza: `test_idor_docente_cannot_touch_other_teacher_resources` (sweep su
~25 endpoint con docente estraneo → 403/404).

### IDOR `/learn/classi/*`
Trait `ResolvesScholaAccess`: `assertActiveEnrollment` (solo `status=active`) +
`assertPublicationInClass`. Coperti anche **player audio/sorgente**, **polling
stato generazione**, **auto-generazione**, **chat di classe**. I quiz Schola
(`course/module NULL`) hanno guard dedicato in `QuizController` (pubblicato in
classe attiva o auto-generato dall'autore). Evidenza:
`test_idor_student_non_active_denied_everywhere` (pending/removed/estraneo →
403 ovunque) + `StudentFruitionTest`.

### Storage privato / path traversal
Sorgenti e file serviti **solo** via controller con `abort_unless` owner/enrollment;
il path proviene da `source_files[$index]`/`[0]` salvati in DB, mai da input
utente → nessun `../` possibile. `response()->file` con Range per il seek audio.

### Enumerabilità codici invito
`ClassJoinController`: messaggio **identico** (`GENERIC_CODE_ERROR`) per codice
inesistente / disabilitato / classe archiviata → nessun leak di esistenza.
`throttle:class-join` (8/min) anti brute-force.

### Mass assignment
I controller Schola usano array di campi **espliciti** in `create()`/`update()`
(mai `$request->all()`); i campi sensibili (`teacher_id`, `student_id`,
`shared_with_teachers`, `origin_artifact_id`, `status`, `quiz_id`) sono impostati
server-side, non dalla request.

### CSRF
Tutte le mutazioni (POST/PATCH/DELETE) passano dal middleware `web`
(`VerifyCsrfToken`); i form async usano `@csrf`, la chat invia l'header
`X-CSRF-TOKEN`. Gli endpoint di polling sono **GET** idempotenti (nessuno stato
mutato), quindi fuori dal perimetro CSRF.

---

## Regressione mondo Officina (corsi/formatori/admin)
Confermata: vedi sezione dedicata nel report di chiusura del pacchetto. Suite
completa verde; i percorsi corsi (chat/quiz/certificati) e admin invariati — gli
inserti Schola in `ChatController`/`QuizController` sono rami additivi che
scattano solo nel contesto Schola.
