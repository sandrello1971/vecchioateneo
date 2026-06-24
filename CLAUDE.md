# CLAUDE.md — noscite-atheneum

Guida per le sessioni di sviluppo su questo progetto (Laravel 12 / PostgreSQL 17).
Codice in inglese, commenti e UI in italiano.

---

## Modulo Schola

### Cos'è
Verticale per **scuole superiori** dentro Officina. Attori:
- **professor** — nuovo `role` su `students` (distinto da `instructor` dei corsi).
- **studenti di classe** — iscritti a una classe tramite codice invito.

Pipeline dei contenuti:
`teaching_documents` (grezzo: audio/PDF/foto/YouTube/testo) →
`teaching_artifacts` (lavorato: summary/mindmap/conceptmap/quiz/outline) →
`artifact_publications` (pubblicazione per classe) →
indicizzazione **RAG con scope `class`**.

Riferimento di progettazione: `docs/schola/SPEC.md` (leggere SEMPRE, inclusa la
**nota §0 sullo stato reale del RAG**).

### AMBIENTE — regole assolute
- Si lavora **SOLO** in `/home/noscite/noscite-websites/noscite-atheneum`.
  Un **branch per pacchetto** (`schola/<nome>`), merge su `main` a pacchetto chiuso.
- `/var/www/noscite-atheneum` è **PRODUZIONE**: mai modificarla, mai eseguirci
  comandi. `atheneum_db` è il **DB di produzione**: mai migrazioni né scritture.
- Sviluppo su **`atheneum_dev_db`**, test su **`atheneum_test_db`** (phpunit già
  configurato per pgsql). Staging: **https://dev.atheneum.noscite.it** (basic auth).
- Il `sudo` disponibile copre **solo** `chown`/`chmod`/`setfacl`. Per **nginx,
  certbot, apt, postgres** (creazione DB, estensioni, ecc.) **chiedere all'utente**.
- **Deploy videoai prod = `noscite-videoai/deploy-videoai.sh`, mai rsync a
  mano**: lo script preserva `.env`/`data/`/`venv`. Dopo: installare le deps
  nuove di `requirements.txt` nel venv condiviso `/home/noscite/venv` e
  `sudo systemctl restart noscite-videoai`.
- **Worktree `/home/noscite/worktree-videoai`**: resta in **detached HEAD**
  quando inattivo; le sessioni videoai vi creano i propri branch. **Mai tenere
  `main` checked-out nel worktree** (bloccherebbe i merge dal repo principale).
- **RAG vettoriale in prod ATTIVO** (mini-deploy videoai svolto): `/api/embeddings`
  live su `:8001`, `CREATE EXTENSION vector` e backfill (707/707) fatti su
  `atheneum_db`. Schola può essere attivato.

### Convenzioni del codebase (rispettare SEMPRE)
- **PK `uuid`** con `gen_random_uuid()`, relazioni `foreignUuid`, **CHECK
  constraint via `DB::statement`** (vedi migrazioni esistenti).
- **Defense in depth a 3 livelli**: visibilità UI + `abort_unless` nel controller
  + file in `storage/app/private` (mai accessibili via URL diretto, sempre serviti
  da un controller).
- **Servizi AI**: pattern di `MindMapGenerationService` —
  `Http::post` su `https://api.anthropic.com/v1/messages`, modello
  `claude-sonnet-4-5`, gestione errori con `RuntimeException`, log; chiave da
  `config/services.php` (`ANTHROPIC_API_KEY`). Vedi anche
  `ConceptMapGenerationService`, `QuizGeneratorService`.
- **Lavoro asincrono**: job Laravel sulla tabella `jobs` esistente.
- **Lingua**: commenti e nomi UI in **italiano**, codice in **inglese**.
- **Ogni pacchetto si chiude con suite verde su `atheneum_test_db`**
  (`php artisan test`).

### Vincolo di prodotto AI (NON negoziabile)
Per gli **studenti di classe**, Minerva risponde **SOLO** da chunk
`documents_rag` con `scope='class'` delle loro **classi attive**, con
**retrieval gate**: se il retrieval non produce contesto sufficiente, il modello
**NON viene chiamato** e la domanda finisce in `unanswered_questions`.
Gli studenti di classe non devono MAI ricevere chunk `platform`/`instructor_only`/
`teacher_private` né di classi altrui.

### Feedback UX — NON negoziabile
Ogni operazione che non si completa istantaneamente (generazioni AI, estrazione,
upload, ingestion, pubblicazione) deve dare evidenza visiva **IMMEDIATA** al click
e **CONTINUA** fino al completamento: bottone disabilitato + spinner, elemento in
stato "in corso" che compare subito nella lista, polling che lo aggiorna a
pronto/fallito senza richiedere refresh manuale. Doppio submit sempre prevenuto
(guard client + server). Nessuna feature async si considera completa senza questo.

### Stato reale RAG (aggiornato — prerequisito pacchetto 6 svolto)
RAG vettoriale implementato (sessione pre-6):
- **pgvector installato e `CREATE EXTENSION vector`** su **dev** e **test** (NON
  ancora in **prod**: arriverà al deploy). `documents_rag.embedding` =
  `vector(768)` + indice **HNSW cosine** (migrazione con skip esplicito via
  `App\Support\PgVector::available()` quando l'estensione manca → prod-safe).
- **Embedding**: modello `paraphrase-multilingual-mpnet-base-v2` (768d,
  multilingue) servito da videoai `POST /api/embeddings`; client
  `EmbeddingService` (dimensioni da `config services.embeddings`).
- **Retrieval vettoriale** in `RagService` (coseno + soglia
  `schola.rag_min_similarity`) dietro flag: `rag_vector_enabled_schola`
  (default **true**), `rag_vector_enabled_corsi` (default **FALSE** — il mondo
  corsi resta su ILIKE finché non validato). Fallback ILIKE quando l'embedding
  non è praticabile (videoai giù / colonna assente). I nuovi chunk sono
  embeddati alla creazione; se videoai è giù, `EmbedDocumentChunksJob` li
  recupera (l'ingestion non si blocca). Backfill: `php artisan schola:backfill-embeddings`.
- **CI** su immagine `pgvector/pgvector:pg17`.

Da completare al **deploy prod**: installare pgvector + `CREATE EXTENSION` su
`atheneum_db`, eseguire la migrazione e il backfill. Dettagli: `docs/schola/SPEC.md` §0.

### Agente proattivo (imminente)
Ogni feature che produce dati di attività studente deve scrivere su **tabelle
interrogabili** (mai solo log), perché saranno input dell'agente. Le **aggregazioni**
vanno in **service dedicati riusabili** (es. `app/Services/Schola/...`), non inline
nei controller.

### Separazione dei mondi
Corsi/formatori Officina e Schola **non condividono** scope RAG né interfacce. Non
modificare i comportamenti esistenti di `/learn` per gli studenti dei corsi e di
`/admin`, se non dove esplicitamente richiesto.

### Debito tecnico noto
- **Broadcasting/Echo** incompleto — vedi `docs/tech-debt.md`.
- **`routes/auth.php`** è scaffolding Breeze **orfano** (non incluso da
  `bootstrap/app.php`, che instrada solo `routes/web.php`): l'app usa auth custom
  `admin.login`/`student.login`.
