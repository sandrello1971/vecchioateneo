# Officina — Documentazione del prodotto

Officina (Laravel 12 / PostgreSQL 17) ospita due mondi separati che condividono
auth e infrastruttura ma **non** scope dati né interfacce:

- **Corsi / formazione** (`/learn`, `/admin`): corsi, moduli, quiz, certificati,
  chat Minerva di corso, messaggistica formatore↔discente.
- **Schola** (verticale scuole superiori): docenti, classi, lezioni, Minerva di
  classe vincolata ai materiali. Aree `/docente`, `/scuola`, e lato studente
  dentro `/learn/classi`.

Convenzioni e regole operative: vedi `CLAUDE.md`. Sicurezza/RAG: `docs/schola/`.

---

## Modulo Schola — stato per fase

### Fase 1 — Fetta 1 (docente libero)
Docente (`role=professor`), classi a codice invito, materiali grezzi
(`teaching_documents`) → artefatti (`teaching_artifacts`) → pubblicazione su
classe → RAG `scope='class'` con gate §5 e Minerva di classe. Auto-generazione
studente (mindmap/quiz) e biblioteca docenti. Spec: `docs/schola/SPEC.md`.

### Fase 2 — Scuole e tenancy (P11–P16)
Modello scolastico puro: la **segreteria** (`school_admin`) possiede anagrafiche,
classi e **cattedre** (`teaching_assignments`); il docente vi lavora dentro. Trait
`BelongsToSchool` + `TeacherClassAccess` (cattedra vs proprietà). Import massivo,
credenziali duali (email/username), GDPR (DPA, export, retention). Spec:
`docs/schola/SPEC-FASE2.md`; audit: `docs/schola/SECURITY_AUDIT_FASE2.md`.

### Fase 3 — Le Lezioni (P18–P23) — COMPLETA
Gerarchia **Materia → Argomento → Lezione → Materiali**.

| Pacchetto | Contenuto |
|---|---|
| P18 | Schema Lezioni (`topics`, `lessons`, `lesson_publications`, `lesson_presentations`); UI docente argomenti/lezioni + classificazione materiali |
| P19 | Composizione AI del corpo lezione da N fonti; editing; rigenera; artefatti di lezione |
| P20a | Pubblicazione su classe (cattedra/proprietà) + ingestion RAG `scope='class'` (`LessonRagIngestor`, `RagChunker`) |
| P20b | Fruizione studente: vista Argomento→Lezione, appunti per paragrafo (docente condivise + personali private), ricerca video, **Minerva di lezione** (gate §5) |
| P20c | Quiz di autoverifica auto-generato dallo studente (privato) |
| P21 | Presentazioni `.pptx` (python-pptx) con download gated da storage privato |
| P22 | Messaggistica di classe: thread studente↔docente + annunci broadcast (mirror corsi, tabelle dedicate) |
| P23 | Indurimento: IDOR sweep, XSS/UGC, rate-limit AI, KaTeX self-hosted, as-built |

Spec as-built: `docs/schola/SPEC-FASE3.md`; audit: `docs/schola/SECURITY_AUDIT_FASE3.md`.

**Vincolo RAG (§5) invariato in tutte le fasi**: lo studente di classe riceve
risposte SOLO dai chunk `scope='class'` delle sue classi attive; fuori-KB → niente
modello + `unanswered_questions`.

---

## Deploy
`deploy-atheneum.sh` (rsync ufficiale, preserva `.env`): `npm ci && build`,
`migrate --force`, `db:seed --class=SubjectSeeder --force` (idempotente),
cache config/route/view, reload php-fpm. Videoai: `noscite-videoai/deploy-videoai.sh`.

### Prerequisiti di produzione noti
- **pgvector** + `CREATE EXTENSION vector` su `atheneum_db` (fatto).
- **python-pptx** nel venv di produzione (presentazioni P21).
- **DPA legale** sul flusso chat→Claude prima del primo studente reale.
