# Registro dei trattamenti — Video-AI (indicizzazione e ricerca semantica nei video didattici)

> ⚠️ **BOZZA tecnica/organizzativa — NON è un parere legale.**
> Documento redatto dal team tecnico per descrivere fedelmente *come* il sistema tratta
> i dati. Va **validato con un consulente privacy / DPO** prima dell'uso con dati reali,
> in particolare con **dati di minori**. I punti marcati **[DA VALIDARE]** richiedono
> conferma legale.

_Ultimo aggiornamento bozza: 2026-06-25._

---

## 1. Finalità del trattamento

Indicizzazione e **ricerca semantica** all'interno dei video didattici della piattaforma
(modulo Schola — scuole; e Officina — formazione business). Obiettivo: permettere a
studenti/corsisti di cercare un argomento dentro un video e saltare al punto esatto
("cerca → seek"), e abilitare risposte basate sul contenuto del video.

Il trattamento si articola in: estrazione/derivazione del testo del video → calcolo di
**embedding** (rappresentazioni vettoriali) → archiviazione in un indice vettoriale →
ricerca per similarità su richiesta dell'utente.

---

## 2. Categorie di dati trattati

- **Contenuto audio (parlato)** dei materiali video/audio.
- **Contenuto visivo (fotogrammi)** dei materiali video; testo a schermo.
- **Immagini** caricate (foto di lavagne, appunti, documenti).
- Per i materiali **CARICATI** in ambito scolastico, il contenuto **può includere voci e
  immagini di minori** (studenti ripresi/registrati).
- Metadati tecnici: timestamp, durata, titolo del materiale, riferimenti a lezione/modulo.

> Nota di minimizzazione: per i video **GENERATI** dalla piattaforma, il testo indicizzato
> è il **copione** e il **contenuto delle slide** — che **non devono contenere dati
> personali degli studenti** (vincolo di prodotto, vedi §6).

---

## 3. I due flussi (tecnicamente distinti)

### 3.1 Video **GENERATI** dalla piattaforma — *privacy-lighter*

- Sono i video narrati prodotti dalla piattaforma da una presentazione (slide + copione).
- L'indicizzazione usa **testo GIÀ NOTO**: il copione (parlato) e il testo delle slide
  (visivo), con i timestamp calcolati al montaggio.
- **NESSUN invio a Whisper o a Vision.** Nessuna trascrizione automatica, nessuna analisi
  dei fotogrammi.
- Si calcolano **solo embedding locali** (modello `paraphrase-multilingual-mpnet-base-v2`,
  768 dimensioni) **on-premise** sul servizio interno `videoai`; il vettore è salvato in un
  indice locale (ChromaDB), una collection dedicata per video.
- **Sub-processori esterni coinvolti: nessuno** per l'indicizzazione.
  (La voce del video è sintetizzata a monte via TTS — vedi §4, ElevenLabs — ma a partire dal
  copione, non da dati studente.)
- **Gate compliance**: nessun blocco DPA (non escono dati verso terzi).

### 3.2 Materiali **CARICATI** — *richiedono sub-processori esterni*

- Sono materiali audio/video/foto/YouTube caricati dall'utente.
- Il contenuto viene inviato a **sub-processori esterni**:
  - **audio/video → trascrizione vocale (Whisper, via Groq)**;
  - **fotogrammi / immagini / PDF scansionati → analisi visiva (Vision, via Anthropic)**.
- Il testo così ottenuto è poi trasformato in **embedding locali** (come sopra) e indicizzato.
- **Whisper e Vision ricevono il contenuto effettivo** del materiale (audio, immagini).
- **Gate compliance (attivo nel sistema)**: in ambito **scolastico** (docente associato a una
  scuola), l'elaborazione dei tipi che usano sub-processori esterni
  (`audio`, `youtube`, `photos`) è **BLOCCATA** finché la scuola non ha registrato il
  **consenso DPA video-AI** (`schools.video_ai_dpa_accepted_at`). Senza consenso il materiale
  non viene inviato ad alcun sub-processore e, di conseguenza (gate "pubblicabile =
  interrogabile"), il relativo contenuto non diventa interrogabile/pubblicabile.
  I tipi locali (`pdf` testuale, `docx`, `text`) non passano da sub-processori esterni e
  restano disponibili.

---

## 4. Sub-processori

| Sub-processore | Cosa riceve | Finalità | Dove / extra-UE | Flusso |
|---|---|---|---|---|
| **Groq** (Whisper STT) | traccia audio del materiale caricato (audio/video/YouTube) | trascrizione vocale → testo | **[DA VALIDARE]** region/extra-UE | Caricati |
| **Anthropic** (Vision / LLM) | fotogrammi / immagini / PDF scansionati del materiale caricato | descrizione/estrazione testo dall'immagine | **[DA VALIDARE]** region/extra-UE | Caricati |
| **ElevenLabs** (TTS) | il **copione** del video generato (testo) | sintesi vocale della narrazione | **[DA VALIDARE]** region/extra-UE | Generati (non riceve dati studente) |

- **Embedding**: calcolati **on-premise** (servizio `videoai`, modello mpnet), **nessun
  sub-processore esterno**.
- **Indice vettoriale** (ChromaDB) e **file dei materiali**: storage privato on-premise.

> **[DA VALIDARE]** Per ciascun sub-processore: presenza di un accordo ex art. 28 GDPR
> (responsabile/sub-responsabile), localizzazione del trattamento (UE/extra-UE), garanzie
> per i trasferimenti (SCC/adeguatezza), tempi di conservazione lato fornitore, uso o meno
> dei dati per addestramento dei loro modelli.

---

## 5. Base giuridica

**[DA DEFINIRE col consulente]** — tipicamente:

- Rapporto con la **scuola titolare** del trattamento: contratto / nomina a responsabile
  (art. 28 GDPR) della piattaforma; i sub-processori come sub-responsabili.
- Per i **minori**: **[DA VALIDARE]** consenso dei genitori/tutori e/o base giuridica
  dell'istituzione scolastica; informativa dedicata.
- Per Officina business: contratto col cliente/titolare; di norma nessun minore coinvolto.

---

## 6. Misure tecniche e organizzative

- **Storage privato**: i file dei materiali e i video sono serviti solo da controller con
  controllo d'accesso (mai URL pubblici diretti).
- **Embedding on-premise**: le rappresentazioni vettoriali sono calcolate localmente; non
  escono verso terzi.
- **Gate DPA per i caricati Schola** (attivo): nessun invio a Whisper/Vision senza consenso
  registrato della scuola; backstop anche a livello di job (difesa in profondità).
- **Minimizzazione (generati)**: il **copione non deve contenere dati personali dello
  studente**; l'indicizzazione dei generati usa solo copione + testo slide.
- **Gate "pubblicabile = interrogabile"**: un video è pubblicato (visibile a studenti/
  corsisti) solo se indicizzato; ne consegue che ciò che è visibile è coerente con l'indice.
- **Accesso alla ricerca**: per-video, con gate (iscrizione attiva + materiale pubblicato);
  nessuna ricerca cross-video lato discente.
- **Separazione degli ambiti**: Schola (scuole, minori) e Officina (business) restano
  distinti; il gate DPA si applica all'ambito scolastico.

---

## 7. Conservazione

**[DA VALIDARE]** Definire i tempi di conservazione di: file sorgente dei materiali,
trascrizioni/testi estratti, embedding nell'indice vettoriale, e la procedura di
cancellazione (incl. propagazione ai sub-processori, ove applicabile).

---

## 8. Diritti degli interessati

**[DA VALIDARE]** Procedura per accesso, rettifica, cancellazione, opposizione e portabilità,
con particolare attenzione ai contenuti che ritraggono minori; modalità di esercizio tramite
la scuola titolare; tempi di risposta.

---

## 9. Trasferimenti extra-UE

**[DA VALIDARE]** Verificare se Groq (Whisper) e Anthropic (Vision) — ed ElevenLabs (TTS) —
comportino trasferimenti extra-UE e con quali garanzie (clausole contrattuali standard,
decisioni di adeguatezza, misure supplementari). Indicare la region effettiva di ciascun
servizio.

---

## 10. Sezione "DA VALIDARE" — riepilogo dei punti che richiedono conferma legale

1. **Base giuridica** del trattamento (scuola titolare; minori: consenso genitori/tutori).
2. **Accordi art. 28** con Groq, Anthropic, ElevenLabs (sub-responsabili) e relative garanzie.
3. **Localizzazione e trasferimenti extra-UE** per ciascun sub-processore; SCC/adeguatezza.
4. Eventuale **uso dei dati per addestramento** da parte dei fornitori (deve essere escluso).
5. **Tempi di conservazione** e procedura di **cancellazione** (incl. presso i sub-processori).
6. **Diritti degli interessati** e modalità di esercizio per contenuti con minori.
7. **Informativa** dedicata per studenti/genitori in ambito scolastico.
8. Conferma che il **gate DPA** implementato è sufficiente come misura, o se serve un consenso
   più granulare (per-classe / per-materiale anziché per-scuola).
9. Verifica che il **copione dei video generati** non contenga di fatto dati personali
   (controllo di processo/contenuto).

---

### Riferimenti tecnici (dove vive ciò che è descritto qui)

- Indicizzazione generati (testo noto, no sub-processori): `app/Services/Schola/VideoIndexService.php`,
  endpoint `videoai POST /api/videos/{id}/index_chunks`.
- Indicizzazione caricati (Whisper/Vision): `app/Services/VideoAIService.php`
  (`ingestVideo`, `transcribeAudio`, `transcribeYouTube`), `videoai /api/videos/ingest`.
- Estrazione materiali Schola (audio→Whisper, foto/PDF→Vision): `app/Services/Schola/TeachingDocumentExtractor.php`,
  `app/Jobs/ExtractTeachingDocumentJob.php`.
- Gate DPA: `app/Support/VideoAiConsent.php`, flag `schools.video_ai_dpa_accepted_at`.
- Ricerca per-video con gate d'accesso: `app/Services/Schola/VideoSearchService.php`.
