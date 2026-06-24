# Guida — Come strutturare i documenti da caricare

Questa guida spiega **come deve essere fatto un documento** affinché il sistema lo
interpreti e lo **suddivida automaticamente** nel modo corretto. Le regole qui
sotto non sono indicazioni generiche: corrispondono esattamente al motore di
parsing (`app/Services/CourseDocumentParser.php`) e di chunking
(`app/Services/Schola/RagChunker.php`).

Esistono **due tipi di caricamento**, con regole diverse:

1. **Manuale corso** (Admin → caricamento corso): il documento viene **diviso in
   moduli/capitoli**. Qui la struttura è fondamentale. → [Sezione A](#a-manuale-corso-docx--moduli)
2. **Materiali / documenti per la knowledge base RAG** (PDF, DOCX, foto, audio,
   YouTube, testo): vengono spezzati in piccoli chunk per la ricerca. La struttura
   conta meno ma aiuta la qualità delle risposte. → [Sezione B](#b-documenti-per-la-knowledge-base-rag)

---

## A. Manuale corso (DOCX → moduli)

### Formato del file
- **Estensioni accettate:** `.docx` (consigliato) o `.doc`. **No PDF** per il
  manuale: la suddivisione in moduli funziona solo da Word.
- **Dimensione massima:** 50 MB.
- (Facoltativo) file **esame** separato: `.docx`/`.doc`, max 20 MB.

### Come avviene la suddivisione
Il sistema converte il Word in HTML e cerca i **titoli** per spezzare il documento.
Un titolo viene riconosciuto in **due modi** (puoi usare l'uno o l'altro, o
entrambi — il secondo è il più robusto):

1. **Stile titolo di Word** — applica al paragrafo lo stile *Titolo 1 / Titolo 2 /
   Titolo 3* (Heading 1/2/3).
2. **Paragrafo in grassetto** scritto **da solo su una riga**, con un testo che
   segue le convenzioni di denominazione qui sotto.

> ⚠️ Un titolo NON viene riconosciuto se è grassetto **in mezzo** ad altro testo
> nello stesso paragrafo. Deve essere un paragrafo a sé, tutto in grassetto (o con
> lo stile Titolo).

### Convenzioni di denominazione dei titoli

| Livello | A cosa serve | Come deve iniziare il titolo | Esempi validi |
|--------|--------------|------------------------------|----------------|
| **1 — Modulo** | crea un **nuovo modulo** | una di queste parole: **PARTE, MODULO, LEZIONE, UNITÀ, SEZIONE, ARGOMENTO** seguita da un ordinale (PRIMA, SECONDA…) o da un numero (1, 2… oppure I, II, III…) | `MODULO 1 — Fondamenti`, `PARTE PRIMA`, `LEZIONE 3 – Le reti`, `UNITÀ II` |
| **2 — Capitolo** | sotto-sezione dentro il modulo | la parola **Capitolo** seguita da un numero | `Capitolo 1`, `Capitolo 4 — Il metodo` |
| **3 — Paragrafo** | sotto-sezione di dettaglio | un numero in formato **X.Y** seguito dal titolo | `1.2 Definizioni`, `3.4 Esempi pratici` |

Note importanti:
- Le parole di livello 1 (MODULO, PARTE, ecc.) sono **riconosciute anche in
  minuscolo**, ma scriverle in MAIUSCOLO le rende inequivocabili.
- Ogni titolo di **livello 1 apre un nuovo modulo**. Tutto ciò che sta sotto quel
  titolo (fino al titolo di livello 1 successivo) finisce in quel modulo.
- Se nel documento **non c'è nessun titolo di livello 1** ma ci sono dei
  "Capitolo N", il sistema usa quelli come moduli.

### Il "frontmatter" (intestazione del corso)
Tutto ciò che scrivi **prima del primo titolo di Modulo** viene usato per ricavare
automaticamente (tramite AI) **nome, descrizione breve e descrizione estesa** del
corso.

- Inserisci all'inizio del documento il **titolo del corso** e un breve testo
  introduttivo (di cosa tratta, a chi è rivolto).
- Serve **almeno ~50 caratteri** di testo: se l'intestazione è troppo corta, il
  corso verrà importato come *"Corso senza titolo"* e dovrai compilare i dati a
  mano.
- Il sistema **non inventa**: scrivi esplicitamente i temi che vuoi compaiano nella
  descrizione.

### Sezione di ripasso / preparazione all'esame
Se vuoi una sezione finale di ripasso, mettila **alla fine dell'ultimo modulo**
come titolo di livello 2 (Capitolo / stile Titolo 2) che contenga una di queste
diciture: **"Preparazione all'esame"**, **"Preparazione esame"**, **"Ripasso"** o
**"Recap"**. Verrà automaticamente staccata dal contenuto del modulo e gestita come
materiale di preparazione separato.

### Cosa succede se la struttura non viene riconosciuta
Se il sistema **non trova alcun titolo** valido, **non blocca** l'importazione: crea
un **unico modulo** chiamato *"Contenuto del corso"* con tutto il testo dentro.
Potrai poi suddividerlo a mano dall'admin — ma è meglio strutturarlo a monte.

### Checklist rapida — manuale corso
- [ ] File in `.docx` (non PDF).
- [ ] Prime righe: titolo del corso + intro (≥ 50 caratteri).
- [ ] Ogni modulo inizia con `MODULO N` / `PARTE …` / `LEZIONE N` (grassetto su
      riga propria, o stile Titolo 1).
- [ ] Eventuali capitoli come `Capitolo N` (stile Titolo 2).
- [ ] Eventuali paragrafi come `1.2 Titolo` (stile Titolo 3).
- [ ] Eventuale ripasso finale intitolato "Preparazione all'esame" / "Ripasso".

### Esempio di struttura corretta (manuale)

```
SEGNALE — Fondamenta di AI Operativa            ← titolo corso (frontmatter)
Corso introduttivo rivolto a operatori che...   ← intro (alimenta la descrizione)

MODULO 1 — Concetti di base                     ← grassetto / Titolo 1  → modulo
Capitolo 1                                       ← Titolo 2 → capitolo
1.1 Che cos'è un modello                         ← Titolo 3 → paragrafo
... testo ...
1.2 Come si addestra
... testo ...

MODULO 2 — Applicazioni                          ← nuovo modulo
... testo ...
Capitolo 3 — Preparazione all'esame              ← staccato come ripasso
... domande di ripasso ...
```

---

## B. Documenti per la knowledge base (RAG)

Riguarda i materiali caricati per essere **interrogati dall'assistente** (es.
documenti di piattaforma, materiali del docente, pubblicazioni di classe).

### Formati accettati
- **Documenti:** `pdf`, `doc`, `docx`, `txt`.
- **Materiali docente (Schola):** anche **foto** (jpg/png — trascritte via AI),
  **audio**, **video YouTube** (trascritti), **testo** incollato.
- Dimensioni tipiche: fino a ~20–50 MB a seconda del canale di caricamento.

### Come vengono spezzati (chunking)
Il testo viene diviso in **chunk di circa 420 caratteri** (massimo 480), con una
piccola sovrapposizione tra un chunk e il successivo. Questo perché la ricerca
semantica lavora su frammenti brevi. Conseguenze pratiche su **come scrivere**:

- **Tieni i concetti compatti.** Un'idea che si capisce in 3–4 frasi sta in un
  singolo chunk ed è recuperata bene. Concetti spalmati su pagine intere si
  frammentano e si recuperano peggio.
- **Frasi autoconsistenti.** Evita riferimenti tipo *"come visto sopra"* o *"vedi
  paragrafo precedente"*: un chunk può finire in un assistente senza il contesto
  attorno. Ripeti il soggetto invece di usare pronomi a distanza.
- **Usa titoli e sottotitoli** (anche solo `#`, `##` in testo/markdown): aiutano a
  mantenere insieme i blocchi tematici.
- **Liste e tabelle** vanno bene; testo scansionato come immagine **no** se non è
  leggibile (viene trascritto via AI e gli illeggibili vengono marcati come tali).

### Qualità del testo sorgente
- **PDF nativo / testo selezionabile** è molto meglio di un **PDF scansionato**:
  da un PDF immagine senza testo l'estrazione può risultare **vuota**.
- Per documenti di sole immagini/foto, scrivi in modo nitido e ben illuminato: la
  trascrizione mantiene titoli, elenchi, tabelle e formule (in LaTeX).

### Checklist rapida — documenti RAG
- [ ] PDF con testo selezionabile (non solo scansione immagine), oppure DOCX/TXT.
- [ ] Concetti espressi in frasi brevi e autoconsistenti.
- [ ] Titoli/sottotitoli per separare gli argomenti.
- [ ] Nessun riferimento "al volo" al contesto circostante.

---

## Riepilogo in una riga
- **Manuale corso** → Word, titoli `MODULO/PARTE/LEZIONE N` (grassetto o stile
  Titolo) per la suddivisione in moduli, intro all'inizio per i metadati.
- **Documenti RAG** → testo selezionabile, concetti brevi e autoconsistenti con
  titoli, perché vengono spezzati in frammenti da ~420 caratteri.
