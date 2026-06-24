# Schola Fase 2 — Security Audit (P16, 2026-06-08)

Audit della tenancy multi-scuola (modello scolastico) e degli adempimenti GDPR.
Pacchetti P11–P16. Evidenza in `tests/Feature/Schola/TenancyHardeningTest.php`,
`SchoolTenancyTest.php`, `SchoolAreaTest.php`, `Teacher/StudentImportTest.php`,
`SchoolClassCattedraTest.php`, `GdprTest.php`.

## Sintesi per categoria

| Categoria | Esito | Note |
|---|---|---|
| Isolamento `/scuola/*` (IDOR cross-scuola) | ✅ PASS | ogni rotta parametrizzata chiama `assertSameSchool`; le liste sono scoped da `currentSchool()` |
| Impatti `/docente` cross-scuola | ✅ PASS | classi via `TeacherClassAccess` (cattedra/proprietà); B non vede/pubblica su classi di A |
| Confine roster (segreteria vs docente) | ✅ PASS | roster classi di scuola mutabile SOLO da `/scuola`; docente in sola lettura |
| Pubblicazione (cattedra vs proprietà) | ✅ PASS | classe scuola → cattedra obbligatoria; classe libera → proprietà (byte-identica fetta 1) |
| Credenziali studenti (duali) | ✅ PASS | lista one-time consumata al download; password hashate a DB |
| Export dati scuola | ✅ PASS | scoped `school_id`; download della sola scuola; file consumato |
| Retention fine anno | ✅ PASS | guard `--school`+`--school-year` obbligatori, dry-run di default, `--force` per scrivere |
| Login duale (email/username) | ✅ PASS | username UNIQUE globale; nessun bypass |
| Storage privato (logo/export/sorgenti) | ✅ PASS | serviti via controller con check appartenenza; mai URL diretto |
| Mass assignment | ✅ PASS | create/update con array espliciti; `school_id`/`role` impostati server-side |

Legenda: ✅ PASS conforme. Nessuna vulnerabilità nuova trovata in P16.

---

## 1. IDOR sweep multi-scuola — esito

Sweep sistematico (due scuole A e B) su OGNI rotta `/scuola` parametrizzata,
con `school_admin` di B che tenta di leggere/mutare risorse di A:

- `classi.show/update/students/cattedre.store`, `cattedre.destroy` → **403**.
- `docenti.import.commit/discard`, `studenti.import.commit/result/credentials/discard` → **403**.
- `scuola.logo/{school}` di A → **403**.
- liste non parametrizzate (`docenti.index`, `classi.index`, `privacy.index`)
  → mostrano **solo** i dati della scuola dell'utente (B non vede risorse di A).
- stato di A **invariato** dopo gli attacchi.

Impatti `/docente`: un docente di B non vede la classe di A nell'indice, non
può aprirne la vista/Minerva (**403**) né pubblicarvi (**403**).

**Causa strutturale della tenuta**: tutte le rotte parametrizzate passano da
`ResolvesSchoolAccess::assertSameSchool($risorsa)`; le liste da
`ResolvesSchoolAccess::currentSchool()` / `forSchool()`; gli accessi docente da
`TeacherClassAccess` (cattedra o proprietà). Nessun controller `/scuola`
accetta un `school_id` da input. **Nessun finding** → nessun commit di fix.

## 2. GDPR — adempimenti

- **DPA** (`schools.dpa_signed_at`): marcabile/revocabile dalla segreteria;
  banner nel dashboard e pagina Privacy se non firmato. Senza DPA la scuola
  resta **operativa** ma **segnalata** (warning, non blocco — decisione P16).
- **Export dati scuola** (`/scuola/privacy/export`): job `ExportSchoolDataJob`
  → JSON in storage privato, scoped `school_id` (docenti, studenti, classi,
  cattedre, conteggi). Download della **sola** scuola, file consumato
  (`deleteFileAfterSend`). Per accesso/portabilità.
- **Retention** (`schola:retention`): anonimizzazione PII degli studenti
  **usciti** di un anno chiuso. **Policy esplicita**: bersaglio = studenti con
  iscrizione nelle classi dell'anno indicato e **senza** iscrizione `active` in
  una classe non archiviata; azione = PII azzerata (nome/email/username/
  data_nascita), account disattivato, riga conservata per integrità; **materiali
  docente conservati**. Guard: `--school` e `--school-year` **obbligatori**,
  **dry-run di default** (elenca senza scrivere), `--force` per eseguire.
- **Audit import**: gli `import_batches` (chi/quando/cosa) restano consultabili
  in `/scuola/privacy` come traccia degli inserimenti di anagrafiche di minori.

## 3. Regressione mondo Officina + fetta 1
Confermata: suite completa verde; corsi/formatori/admin invariati; pubblicazione
e flussi dei docenti liberi (`school_id` NULL) byte-identici a fetta 1.
