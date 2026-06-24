# Officina Schola — Fase 2: Scuole, segreteria e tenancy

> Estende la fetta 1 (completa) con lo strato multi-tenant.
> **Decisione fondante (07/06): modello scolastico PURO** — la segreteria
> possiede anagrafiche, classi e cattedre; il docente riceve le assegnazioni
> e vi lavora dentro, non crea classi. Il flusso a codici invito di fetta 1
> sopravvive SOLO per i docenti "liberi" (school_id NULL).

---

## AS-BUILT (aggiornato 2026-06-08, pacchetto 16) — fase 2 COMPLETA

Tutto in produzione (codice), dormiente finché non si crea una scuola.

| Sezione / Pacchetto | Stato | Note di realizzazione |
|---|---|---|
| §1 Gerarchia 3 livelli (P11) | ✅ FATTO | ruolo `school_admin`; admin `/admin/scuole` crea scuola + nomina segreteria |
| §2 Isolamento (P11–P16) | ✅ FATTO | trait `BelongsToSchool` (scope) + `ResolvesSchoolAccess` (controller); IDOR sweep verde, `SECURITY_AUDIT_FASE2.md` |
| §3 Cambia-vs-fetta1 (P15) | ✅ FATTO | pubblicazione/accesso via `TeacherClassAccess` (cattedra/proprietà); docenti liberi byte-identici; roster scuola = solo segreteria |
| §4 Migrazioni | ✅ FATTO | schools, teaching_assignments, import_batches, professor_subjects; students.school_id/username/email-nullable; class_students.consent_at; school_classes teacher_id/subject_id nullable |
| §5.1 Admin `/admin/scuole` (P11) | ✅ FATTO | CRUD + nomina school_admin |
| §5.2 Area `/scuola` (P12–P16) | ✅ FATTO | dashboard, anagrafica+branding, docenti, studenti, classi/cattedre, privacy |
| §5.3 Impatti `/docente` (P15) | ✅ FATTO | classi-cattedra; "Crea classe" gated; classi scuola read-only |
| §6 Import massivo (P13/P14) | ✅ FATTO | preview→commit; credenziali duali (email/username); minori; classi da creare |
| §7 P16 GDPR + hardening | ✅ FATTO | DPA, export dati, `schola:retention` (dry-run+force), audit import, IDOR sweep |
| §8.1 credenziali (variato) | ✅ FATTO | **duale confermato**: login email O username |
| §8.2 consenso | ✅ FATTO | piattaforma non lo raccoglie; DPA + `consent_at` opzionale; nota legale chat→Claude |
| §8.3 anno scolastico | ⏸ DIFFERITO | passaggio anno automatico → fase 2.1 (la retention copre la chiusura) |
| §8.4 SSO/registro elettronico | ⏸ DIFFERITO | import via API (Argo/ClasseViva/SIDI) → fase 3 |

**Comandi fase 2**: `schola:retention --school --school-year [--force]`,
export via `/scuola/privacy`. **Seed**: `ScholaDemoSeeder` ora include 2 scuole
complete (oltre al docente libero).

---

## 1. Gerarchia a tre livelli

```
Admin piattaforma (Noscite)   → crea/sospende le Scuole, nomina il primo school_admin
        Scuola (school_admin) → anagrafiche docenti+studenti, classi, cattedre,
                                 branding, adempimenti GDPR
                Professore    → riceve le cattedre, prepara e pubblica nelle SUE classi-materia
                Studente      → provisioned dalla segreteria, mappato alla sua classe
```

Nuovo ruolo `school_admin` (segreteria). Come gli altri attori vive su
`students` con `role='school_admin'` e `school_id` valorizzato. Non è un
docente: non ha area `/docente`, ha la propria area `/scuola`.

---

## 2. Confine di isolamento (non negoziabile, come il RAG §5)

**Una segreteria vede e opera SOLO sulla propria scuola.** Ogni query del
lato `/scuola` filtrata per `school_id` dell'utente; il platform admin è
l'unico che attraversa le scuole. Da testare con scenari multi-scuola
(IDOR sweep), esattamente come il leak di chunk tra classi in fetta 1.
Implementazione: trait/global scope `BelongsToSchool` + helper
`ResolvesSchoolAccess` (gemello di `ResolvesScholaAccess`).

---

## 3. Cosa cambia rispetto a fetta 1

- **La classe non appartiene più al docente.** In fetta 1 `school_classes.teacher_id`
  era il proprietario. Nel modello scuola la classe appartiene alla SCUOLA;
  i docenti vi sono legati tramite **cattedre** (`teaching_assignments`:
  professore × materia × classe). `school_classes.teacher_id` diventa
  nullable e assume il significato di "coordinatore" (opzionale).
- **La pubblicazione richiede una cattedra.** Un professore può pubblicare un
  artefatto a una classe SOLO se ha una cattedra attiva lì. (In fetta 1
  bastava esserne proprietario.) Per i docenti liberi resta il vecchio
  criterio di proprietà.
- **Confine roster ↔ didattica (regola).** La **segreteria** gestisce
  l'**anagrafica**: chi sta in quale classe (aggiunge/rimuove studenti),
  cattedre, account. Il **docente** opera SOLO sul livello **didattico**
  (materiali, artefatti, pubblicazione, Minerva, monitoraggio) e **vede** il
  roster delle sue classi-cattedra ma **non** aggiunge/rimuove studenti.
  Eccezione docenti liberi (`school_id NULL`): gestiscono il proprio roster
  via codici invito/approvazione, come in fetta 1.
- **Lo studente di scuola non usa il codice invito.** È provisioned e mappato
  dalla segreteria. Il codice invito e il self-join restano attivi solo per
  classi senza `school_id` (docenti liberi).
- **Branding per scuola.** Le impostazioni white-label (assistant_name,
  instance_name, logo) diventano risolvibili a livello di scuola, sopra il
  default piattaforma. Riusa il branding settings-driven già in essere.

---

## 4. Migrazioni

### 4.1 Alterazioni

```php
// students: nuovo ruolo + appartenenza scuola (school_id per TUTTI gli attori scuola)
DB::statement('ALTER TABLE students DROP CONSTRAINT students_role_check');
DB::statement("ALTER TABLE students ADD CONSTRAINT students_role_check
  CHECK (role IS NULL OR role IN ('student','instructor','admin','professor','school_admin'))");

Schema::table('students', function (Blueprint $t) {
    $t->foreignUuid('school_id')->nullable()->constrained('schools')->nullOnDelete();
    $t->index('school_id');
});

// school_classes: la classe è della scuola; teacher_id diventa coordinatore opzionale
Schema::table('school_classes', function (Blueprint $t) {
    // school_id già presente (nullable) da fetta 1
    $t->uuid('teacher_id')->nullable()->change();   // era owner, ora coordinatore opzionale
});
```

### 4.2 Tabelle nuove

```php
// schools
Schema::create('schools', function (Blueprint $t) {
    $t->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
    $t->string('name');
    $t->string('slug')->unique();                 // per URL/identificazione
    $t->string('type');                           // liceo | istituto_tecnico | altro
    $t->string('city')->nullable();
    $t->json('settings')->nullable();             // branding: assistant_name, instance_name, logo_path, ...
    $t->boolean('allow_professor_create_classes')->default(false); // modello puro: false
    $t->string('status')->default('active');      // active | suspended
    $t->timestamp('dpa_signed_at')->nullable();   // accordo titolare/responsabile (art.28)
    $t->timestamps();
    $t->softDeletes();
});
DB::statement("ALTER TABLE schools ADD CONSTRAINT schools_type_check
  CHECK (type IN ('liceo','istituto_tecnico','altro'))");
DB::statement("ALTER TABLE schools ADD CONSTRAINT schools_status_check
  CHECK (status IN ('active','suspended'))");

// teaching_assignments (cattedre)
Schema::create('teaching_assignments', function (Blueprint $t) {
    $t->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
    $t->foreignUuid('school_id')->constrained()->cascadeOnDelete();
    $t->foreignUuid('teacher_id')->constrained('students')->cascadeOnDelete();
    $t->foreignUuid('subject_id')->constrained()->cascadeOnDelete();
    $t->foreignUuid('school_class_id')->constrained()->cascadeOnDelete();
    $t->string('school_year', 9);
    $t->timestamps();
    $t->unique(['teacher_id','subject_id','school_class_id','school_year'], 'cattedra_unique');
    $t->index(['school_class_id','subject_id']);
});

// import_batches (caricamenti massivi, con dry-run/preview)
Schema::create('import_batches', function (Blueprint $t) {
    $t->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
    $t->foreignUuid('school_id')->constrained()->cascadeOnDelete();
    $t->foreignUuid('created_by')->constrained('students')->cascadeOnDelete();
    $t->string('type');                  // professors | students
    $t->string('status')->default('previewed'); // previewed | committed | discarded
    $t->string('source_filename')->nullable();
    $t->json('summary')->nullable();     // righe valide, duplicati, errori, mapping classi
    $t->json('rows')->nullable();        // righe normalizzate + esito per riga (per il commit)
    $t->timestamps();
});
DB::statement("ALTER TABLE import_batches ADD CONSTRAINT import_batches_type_check
  CHECK (type IN ('professors','students'))");
DB::statement("ALTER TABLE import_batches ADD CONSTRAINT import_batches_status_check
  CHECK (status IN ('previewed','committed','discarded'))");
```

Nota GDPR/minori (decisione confermata, §8.2): **la piattaforma NON raccoglie
il consenso**. Base giuridica: la **scuola è titolare**, **Noscite responsabile**
del trattamento (art. 28 GDPR). L'attestazione vive a livello di scuola tramite
`schools.dpa_signed_at` (accordo titolare/responsabile firmato). In aggiunta, un
campo **OPZIONALE** `class_students.consent_at` (nullable) per audit interno
della scuola, se la segreteria vuole tracciare il consenso per iscrizione — non
obbligatorio, non bloccante. La cancellazione di fine anno è un comando dedicato
(pacchetto 16), non un campo.

---

## 5. Aree e rotte

### 5.1 Platform admin — estensioni `/admin`
```
GET    /admin/scuole                      lista scuole (tutte)
POST   /admin/scuole                      crea scuola
GET    /admin/scuole/{school}             dettaglio + stato
PATCH  /admin/scuole/{school}             modifica / sospendi
POST   /admin/scuole/{school}/segreteria  crea/nomina il primo school_admin (email + invito)
```

### 5.2 Segreteria — nuova area `/scuola` (middleware: auth + role school_admin, scope school_id)
```
GET    /scuola                            dashboard scuola (conteggi, adempimenti)
GET    /scuola/anagrafica                 dati scuola + branding (settings)
PATCH  /scuola/anagrafica

# Docenti
GET    /scuola/docenti                    elenco docenti della scuola
GET    /scuola/docenti/import             form caricamento CSV
POST   /scuola/docenti/import/preview     dry-run → report (validi/duplicati/errori), nessuna scrittura
POST   /scuola/docenti/import/commit      applica il batch previewed
PATCH  /scuola/docenti/{teacher}          modifica / disattiva

# Studenti
GET    /scuola/studenti                   elenco studenti
GET    /scuola/studenti/import            form CSV (con classe per riga)
POST   /scuola/studenti/import/preview    dry-run → report (incl. risoluzione classi, minori)
POST   /scuola/studenti/import/commit
PATCH  /scuola/studenti/{student}

# Classi e cattedre
GET    /scuola/classi                     elenco classi della scuola
POST   /scuola/classi                     crea classe (nome, anno, coordinatore opz.)
GET    /scuola/classi/{class}             roster + cattedre della classe
PATCH  /scuola/classi/{class}
POST   /scuola/classi/{class}/studenti    assegna/rimuovi studenti
POST   /scuola/classi/{class}/cattedre    assegna docente×materia (crea teaching_assignment)
DELETE /scuola/cattedre/{assignment}

# GDPR
GET    /scuola/privacy                    stato DPA, export dati, retention
POST   /scuola/privacy/export             export dati scuola (job)
```

### 5.3 Impatti sull'area docente `/docente`
- Se il docente ha `school_id`: vede le classi dove ha una **cattedra**, non
  quelle che possiede; il pulsante "Crea classe" è nascosto/disabilitato
  (salvo `schools.allow_professor_create_classes=true`).
- La pubblicazione valida la cattedra (può pubblicare alla classe X solo se
  ha un assignment lì). Docenti liberi: comportamento fetta 1 invariato.
- **Roster in sola lettura per il docente di scuola.** Vede il roster delle
  sue classi-cattedra (per monitoraggio e pubblicazione) ma le azioni di
  anagrafica — aggiungi/rimuovi studente, gestione cattedre — restano alla
  segreteria nell'area `/scuola`. (Docente libero: gestisce il proprio
  roster come in fetta 1.)

---

## 6. Import massivo — formato e regole

CSV con intestazione, encoding UTF-8, separatore `,` o `;` (autodetect).

**Docenti** — colonne: `nome, cognome, email, materie` (materie separate da `|`,
risolte sulla tabella `subjects`; materia ignota → segnalata, non creata
silenziosamente).

**Studenti** — colonne: `nome, cognome, email, data_nascita (YYYY-MM-DD), classe`
(classe risolta per nome sull'anno corrente; classe inesistente → errore di
riga, si offre creazione in blocco previa conferma).

Pipeline a due passi (cultura a gate):
1. **preview/dry-run**: valida tutto, NON scrive, restituisce report —
   righe valide, duplicati per email (con azione: salta/aggiorna), errori di
   formato, mapping classi/materie non risolti, conteggio minori.
2. **commit**: applica solo se l'utente conferma; crea gli account, associa
   `school_id`, mappa classi/cattedre. Idempotente per email/username.

**Credenziali — supporto DUALE (confermato, §8.1)**:
- **Con email**: account con email → link di impostazione password
  (set-password) inviato via email.
- **Senza email**: **username interno generato** (`nome.cognome`, con
  disambiguazione numerica; in alternativa un codice) + **password temporanea**
  distribuita dalla segreteria (stampabile). Nessun indirizzo email richiesto.
- Il **login accetta email O username**. La password temporanea forza il
  cambio al primo accesso.

---

## 7. Sequenza pacchetti (11→16)

- **P11 — Schema scuole + tenancy + admin CRUD scuole.** Migrazioni (§4),
  ruolo `school_admin`, trait/scope `BelongsToSchool`, `/admin/scuole` CRUD +
  nomina primo school_admin. Test: isolamento multi-scuola di base, CHECK
  ruolo, admin crea scuola e segreteria.
- **P12 — Area `/scuola`: shell, dashboard, anagrafica + branding.** Layout
  segreteria, guard di tenancy ovunque, risoluzione branding per scuola sopra
  il default. Test: school_admin vede solo la sua scuola; branding applicato.
- **P13 — Import docenti.** Form CSV, preview/dry-run con report, commit,
  dedup per email, provisioning credenziali, associazione school_id +
  materie. Test: preview non scrive, commit idempotente, materia ignota
  segnalata, dedup.
- **P14 — Import studenti + assegnazione classe.** Come P13 + risoluzione
  classe per riga, campi minori (data_nascita obbligatoria, conteggio minori
  nel report), iscrizione a `class_students` (status active, niente codice).
  Test: minori, classe inesistente, dedup, iscrizione corretta.
- **P15 — Classi e cattedre + rewire pubblicazione.** CRUD classi lato
  scuola, assegnazione studenti, mappatura cattedre (teacher×subject×class).
  Rewire: `/docente` mostra le classi-cattedra; pubblicazione valida la
  cattedra; "Crea classe" nascosto se non consentito. Regressione docenti
  liberi (school_id NULL) invariata. Test: cattedra richiesta per pubblicare,
  docente vede solo le sue cattedre, docente libero invariato.
- **P16 — GDPR + hardening tenancy.** Record DPA, export dati scuola,
  comando `schola:retention` (cancellazione/anonimizzazione fine anno con
  guard e conferma), audit dei batch di import. IDOR sweep multi-scuola su
  TUTTO `/scuola` e sugli impatti `/docente`. Seed demo esteso a 2 scuole.
  Documentazione as-built fase 2.

---

## 8. Decisioni ancora da prendere (prima di P13/P14)

1. ✅ **DECISA — Credenziali studenti: supporto DUALE.** Import con email →
   account email + link set-password. Senza email → **username interno
   generato** (`nome.cognome`/codice) + **password temporanea** distribuita
   dalla segreteria. Il **login accetta email O username**; password temporanea
   forza il cambio al primo accesso. Incide su P14 (schema `students`: lo
   `username` interno e l'email diventano entrambi opzionali ma almeno uno
   presente) e sul controller di login.
2. ✅ **DECISA — Consenso: la piattaforma NON lo raccoglie.** La **scuola è
   titolare**, **Noscite responsabile** (art. 28). Attestazione a livello scuola
   in fase DPA (`schools.dpa_signed_at`, già previsto) + campo **opzionale**
   `class_students.consent_at` per audit interno della scuola. **Nota legale
   (bloccante prima del primo studente reale)**: il **DPA deve esplicitare il
   flusso dati verso il modello AI** (chat Minerva). Stato tecnico: gli
   **embedding sono locali** (modello self-hosted su videoai), ma la **chat va a
   Claude** (Anthropic) — da **validare con il legale** prima del primo studente
   reale (eventuale DPA/sub-responsabile Anthropic, zona dati, retention).
3. **Anno scolastico**: gestione del passaggio anno (le classi/cattedre sono
   per `school_year`) — promozione/archiviazione automatica o manuale? Può
   restare per una fase 2.1.
4. **SSO/registro elettronico** (Argo, Spaggiari/ClasseViva, SIDI): import
   via API invece che CSV — esplicitamente rimandato a fase 3.
