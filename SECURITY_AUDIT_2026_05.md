# Officina — Security Audit 2026-05

Riepilogo cumulativo dei 4 step di hardening sicurezza dell'area studente/formatore di Officina, eseguiti il 2026-05-05. Le fix sono prerequisito per l'integrazione European Digital Credentials + sigillo qualificato eIDAS.

## Sintesi degli step

| Step | Vulnerabilità | Esito |
|---|---|---|
| 1 | Quiz: scoring lato client, `correct_answer` esposto al DOM, server salva score della request | risolto |
| 2 | Certificato: nessun gating reale (codice MD5 deterministico), nessun registro emissioni, nessuna verifica pubblica | risolto |
| 3 | Materiali: link diretti `/storage/...` accessibili senza autenticazione | risolto |
| 4 | SSO Microsoft con possibile takeover via riuso email tenant; `NoteController`/`CanvasController` senza verifica iscrizione al corso | risolto |

---

## 1. Lista file (per dominio)

### Dominio Quiz (Step 1)

| File | Stato | Scope |
|---|---|---|
| `app/Http/Controllers/Student/QuizController.php` | modificato | scoring 100% server-side, validazione attempt_id, anti-replay 409, `QuizAnswer` persistence per audit, `time_spent_seconds` integer, mail in queue |
| `resources/views/student/quiz/show.blade.php` | riscritto | rimosso `correct_answer`/`explanation` dal payload JSON, `answered` keyed by `q.id`, `corrections` map post-submit, no feedback durante quiz |
| `resources/views/student/quiz/result.blade.php` | nuovo | pagina risultato server-rendered (no Alpine), legge da `attempt->answers` |
| `routes/web.php` | modificato | `throttle:5,1` su `student.quiz.submit` |

### Dominio Certificate (Step 2)

| File | Stato | Scope |
|---|---|---|
| `database/migrations/2026_05_05_154900_create_certificates_table.php` | nuovo | tabella `certificates` con cascade differenziato (student=cascade, course/attempt=null) e unique `(student_id, course_id)` |
| `app/Models/Certificate.php` | nuovo | model con `generateCode()` (80 bit entropia, formato `ATH-XXXX-XXXX-XXXX`) |
| `app/Http/Controllers/Student/QuizController.php` | modificato | metodo privato `issueCertificate()` con `firstOrCreate` idempotente + catch `UniqueConstraintViolationException` |
| `app/Http/Controllers/Student/CertificateController.php` | riscritto | gating su Certificate row (no più MD5), `resolveCertificate` helper, snapshot-friendly |
| `app/Http/Controllers/CertificateVerifyController.php` | nuovo | endpoint pubblico verifica per code |
| `app/Mail/CertificationPassedMail.php` | riscritto | nuova signature `(Student, Course, Certificate)`, no allegato PDF, no score nel template |
| `resources/views/emails/certification-passed.blade.php` | riscritto | code in monospace, URL verifica, link download protetto da login |
| `resources/views/pdf/certificate.blade.php` | modificato | usa `$cert->code` (snapshot), aggiunto QR data-URI inline + URL verifica |
| `resources/views/certificate/verify.blade.php` | nuovo | pagina pubblica standalone, email mascherata `s***@dominio` |
| `app/Providers/AppServiceProvider.php` | modificato | named limiter `certificate-verify` per-IP esplicito |
| `routes/web.php` | modificato | rotta pubblica `/certificato/verifica/{code}` con throttle named |
| `composer.json` / `composer.lock` | modificato | `+endroid/qr-code:^5.0` (5.1.0) |

### Dominio Materials (Step 3)

| File | Stato | Scope |
|---|---|---|
| `database/migrations/2026_05_05_160358_move_materials_to_private_disk.php` | nuovo | backfill da `storage/app/public/materials` a `storage/app/private/materials`, filtro `is_instructor_only=false`, `up()` e `down()` simmetrici |
| `app/Http/Controllers/Student/MaterialController.php` | nuovo | `download()` + `canvas()` con `authorizeAccess()` helper (enrollment + demo + instructor-only) |
| `app/Http/Controllers/Admin/MaterialController.php` | modificato | upload e delete da disk `public` a `local` |
| `app/Http/Middleware/DemoRestrictions.php` | modificato | aggiunto `student.material.canvas` alle rotte bloccate per demo |
| `resources/views/student/course/module.blade.php` | modificato | link `/storage/...` sostituiti con `route('student.material.{download,canvas}')` |
| `routes/web.php` | modificato | 2 nuove rotte studente sotto `student.auth + student.password + demo.restrictions` |

### Dominio Auth (Microsoft SSO, Step 4)

| File | Stato | Scope |
|---|---|---|
| `app/Http/Controllers/Student/MicrosoftAuthController.php` | riscritto | callback con 5 rami espliciti (null email, match, mismatch, bind whitelist-only, signup whitelist-only); messaggio utente unificato per gli auth-fail; log strutturati con context |

### Dominio Notes/Canvas authz (Step 4)

| File | Stato | Scope |
|---|---|---|
| `app/Http/Controllers/Student/NoteController.php` | modificato | helper `ensureEnrolledInModule()` chiamato in `save`/`list`/`delete` (delete deriva module da `$note->module`) |
| `app/Http/Controllers/Student/CanvasController.php` | modificato | helper `ensureEnrolledInMaterial()` chiamato in `getData`/`saveData` |

---

## 2. Diff stat aggregato

> Nota: il repository corrente non è inizializzato git. Non è possibile produrre `git diff --stat`. La tabella sotto riporta LOC correnti dei file toccati come surrogato. Una volta versionato (raccomandato — vedi tech debt), il diff netto sarà calcolabile.

| Step | File toccati | LOC totali |
|---|---|---|
| 1 — Quiz | 3 (1 modificato, 1 riscritto, 1 nuovo) + routes | 816 |
| 2 — Certificate | 11 (4 nuovi, 4 riscritti, 3 modificati) + composer + routes | 784 |
| 3 — Materials | 5 (2 nuovi, 3 modificati) + routes | 1.071 |
| 4 — Auth + Notes/Canvas | 3 (1 riscritto, 2 modificati) | 347 |
| Trasversale `routes/web.php` | 1 modificato | 169 |
| **Totale** | **23 file**, di cui **8 nuovi**, **6 riscritti**, **9 modificati** | **3.187** |

Aggiunte di dipendenze: `endroid/qr-code:^5.0`. Migrations da eseguire: 2.

---

## 3. Deploy plan unificato

Big bang con maintenance mode. Tempo stimato: 1-2 minuti su un server con I/O normale.

```bash
cd /var/www/noscite-atheneum

# 1. Maintenance mode con secret per accesso admin durante deploy
php artisan down --message="Aggiornamento sicurezza in corso..." --retry=30

# 2. Pull codice e dipendenze
git pull origin main
composer install --no-dev --optimize-autoloader

# 3. Migrations (esegue create_certificates_table + move_materials_to_private_disk)
php artisan migrate --force

# 4. Cache fresh
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Verifica permessi (la migration di backfill richiede write su entrambe le radici)
ls -la storage/app/public/materials
ls -la storage/app/private/materials

# 6. Worker queue (per CertificationPassedMail)
sudo systemctl restart atheneum-queue   # o equivalente del setup attuale

# 7. Up
php artisan up
```

### Check post-deploy immediati (script-driven)

```bash
# Conta file: dovrebbero specchiarsi
echo "public/materials count (atteso: solo orfani non-DB):"
find storage/app/public/materials -type f | wc -l

echo "private/materials count (atteso: ~33 = numero record DB con is_instructor_only=false):"
find storage/app/private/materials -type f | wc -l

# Verifica migration log
tail -50 storage/logs/laravel.log | grep "Backfill materiali"

# Sanity DB
php artisan tinker --execute="
echo 'certificates table=' . (Schema::hasTable('certificates') ? 'OK' : 'FAIL') . PHP_EOL;
echo 'materials non-instructor count=' . App\Models\Material::where('is_instructor_only', false)->whereNotNull('file_path')->count() . PHP_EOL;
"

# Verifica rotte critiche risolvono
php artisan route:list | grep -E "certificate.verify|student.material|student.quiz"
```

Se uno qualsiasi dei check fallisce, eseguire il rollback (sezione 5) prima di togliere maintenance mode.

---

## 4. Smoke test post-deploy (manuale, ~10 minuti)

Da eseguire subito dopo `php artisan up`, su account reali in produzione (non fixture).

| # | Test | Atteso |
|---|---|---|
| 1 | Login studente normale (`demo@atheneum.noscite.it`) → dashboard → Primus → modulo qualsiasi | dashboard caricata, modulo accessibile |
| 2 | Stesso studente: download di un materiale del modulo | file scaricato |
| 3 | Login Microsoft SSO con account `@noscite.it` | dashboard, no warning in `storage/logs/laravel.log` |
| 4 | Aprire un quiz; in DevTools/Network tab cercare la response del GET `/learn/quiz/{id}` | nessuna occorrenza di `correct_answer` o `explanation` nel JSON |
| 5 | Submit quiz fittizio con risposte casuali → verificare score restituito | score riflette il numero reale di corrette, non un valore manomesso |
| 6 | Aprire `/certificato/verifica/{code}` con un codice reale di un certificato esistente | mostra nome, corso, data, score, codice; email mascherata `x***@dominio` |
| 7 | Aprire `/certificato/verifica/ATH-FAKE-FAKE-FAKE` | "Codice non trovato" |
| 8 | GET diretto in browser anonimo a `https://atheneum.noscite.it/storage/materials/initium/qualcosa.pdf` | 404 (file non più lì) |
| 9 | Aprire un canvas dalla view modulo | renderizza inline, content-type `text/html` |
| 10 | Studente prova URL canvas di corso a cui non è iscritto (cambiando l'UUID nell'URL) | 403 |

**Se uno qualsiasi fallisce**: eseguire immediatamente il rollback (sezione 5).

---

## 5. Rollback plan unificato

Se uno smoke test fallisce o emergono regressioni nei primi minuti:

```bash
cd /var/www/noscite-atheneum

php artisan down --message="Ripristino in corso..." --retry=30

# 1. Rollback migrations (sposta materiali back to public + drop certificates table)
php artisan migrate:rollback --step=2 --force

# 2. Revert codice (sostituire con i 4 commit hash dei rispettivi step)
git revert <hash-step-4> <hash-step-3> <hash-step-2> <hash-step-1> --no-edit

# 3. Dipendenze al precedente lockfile
composer install --no-dev --optimize-autoloader

# 4. Cache
php artisan optimize:clear

# 5. Restart worker (catch failed jobs della vecchia signature mail)
sudo systemctl restart atheneum-queue

# 6. Up
php artisan up

# 7. Verifica: i materiali sono di nuovo su public e accessibili
find storage/app/public/materials -type f | wc -l   # atteso ~33+orfani
find storage/app/private/materials -type f | wc -l  # atteso 0

# 8. Smoke ridotto
# - Login studente → modulo → /storage/materials/...{file} carica
# - Quiz funziona come prima
```

Se il rollback delle migrations fallisce su `move_materials_to_private_disk` (es. permessi env-specifici, vedi tech debt nota nello Step 3), spostare manualmente i file con utente con permessi adeguati (`sudo -u www-data mv ...`) prima di rieseguire `migrate:rollback`.

---

## 6. Cose da monitorare nei primi 7 giorni

### `storage/logs/laravel.log` — grep mirati

```bash
# Tentativi takeover SSO o account legittimi inceppati
grep "SSO:" storage/logs/laravel.log

# Backfill anomalies (rename falliti emergono qui)
grep "Backfill materiali" storage/logs/laravel.log

# Email certificato fallite
grep "Email certificato fallita" storage/logs/laravel.log

# Throttle hit anomali
grep "ThrottleRequests" storage/logs/laravel.log
```

Ogni `SSO: microsoft_id mismatch` o `SSO: bind blocked` va investigato: campanello di tentativo takeover o di account legittimo bloccato per configurazione non aggiornata.

### Tabella `certificates` — sanity

```sql
-- Distribuzione emissioni per corso
SELECT
  certification_name,
  COUNT(*) AS emessi,
  MIN(issued_at) AS prima,
  MAX(issued_at) AS ultima
FROM certificates
GROUP BY certification_name;

-- Eventuali certificati con quiz_attempt_id null (corso/attempt cancellato post-emissione)
SELECT id, code, certification_name, issued_at FROM certificates WHERE quiz_attempt_id IS NULL;

-- Anomalie: stesso studente con multiple certs per stesso corso (non dovrebbe accadere)
SELECT student_id, course_id, COUNT(*)
FROM certificates
WHERE course_id IS NOT NULL
GROUP BY student_id, course_id
HAVING COUNT(*) > 1;
```

### `failed_jobs` — pulizia e indagine

```bash
# La tabella deve restare a zero. Ogni job fallito di CertificationPassedMail va indagato.
php artisan tinker --execute="
echo 'failed=' . DB::table('failed_jobs')->count() . PHP_EOL;
foreach (DB::table('failed_jobs')->orderByDesc('failed_at')->limit(5)->get() as \$f) {
    echo substr(\$f->exception, 0, 200) . PHP_EOL;
}
"
```

### 403 anomali — possibili regressioni di authz

Tail dei log access (nginx) per `403` su path studente. Se un singolo utente reale prende 403 ripetuti su path `/learn/notes/*`, `/learn/canvas/*`, `/learn/material/*`: probabile regressione enrollment edge-case (es. pivot `is_active=false` per disiscrizione manuale dimenticata, oppure `course_id=null` su modulo).

---

## 7. Tech debt aperto

Da affrontare in PR separate post-deploy. Nessuno è bloccante.

- **Trait `ChecksCourseEnrollment`** — la stessa logica enrollment è duplicata in `Student\MaterialController`, `Student\NoteController`, `Student\CanvasController` (~12-15 righe per metodo).
- **`storage/app/public/canvas/`** — 7 file HTML legacy non referenziati (duplicati pre-Step-3 di alcuni canvas).
- **Orfani in `storage/app/public/materials/initium/`** — 3 file (esame finale v4, manuale discente v4, programma corso v4) non in DB.
- **`endroid/qr-code 5.1` deprecation warnings su PHP 8.3** — issue upstream [#538](https://github.com/endroid/qr-code/issues), funzionale ma rumoroso nei log.

Aggiunge inoltre per igiene del progetto:

- **Inizializzazione git del repo** — il working tree non è versionato, impossibile fare `git diff` o tracciare regressioni con bisect. Da fare prima del prossimo deploy non banale.

---

## 8. Cosa NON è stato fatto

Roadmap di sicurezza/qualità identificata nell'analisi iniziale, fuori scope dei 4 step ma da pianificare:

- **Refactor `session('student_id')` → `Auth::guard('student')`** — modernizzazione del custom auth verso il guard standard Laravel.
- **pgvector per RAG** — embeddings su contenuti corso, oggi assente.
- **Anchor stabili per note studente** — l'anchor attuale è fragile a riordini di sezione.
- **Cast `Module::metadata`** — one-liner, non urgente.
- **Streaming video con signed URL nginx** — i video oggi passano dalla pipeline VideoAI; per video diretti servirà signed URL temporaneo.
- **Refactor stili inline → Tailwind classes** — ripulitura tecnica, non incide su sicurezza.
- **Rate limiting su `/learn/demo`** — l'endpoint demo crea sessioni lato server, da proteggere se l'uso si fa rumoroso.

Nessuno è urgente come quelli appena chiusi.

---

*Audit eseguito il 2026-05-05. Documento generato a chiusura dei 4 step di hardening.*
