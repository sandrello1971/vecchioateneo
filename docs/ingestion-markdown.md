# Ingestion manuali in Markdown

Oltre al DOCX, l'ingestion corsi (`/admin/courses/ingest`) accetta manuali in
**Markdown** (`.md` / `.markdown`). Il Markdown è **strutturalmente esplicito**
(`#` è un heading certo) → più affidabile del DOCX, dove gli stili Word sono
ambigui. La conversione usa `pandoc --from=gfm --to=html5`; il resto della
pipeline (split moduli, metadati, esame, anteprima, creazione) è identico al DOCX.

## Struttura attesa

| Elemento Markdown | Effetto nell'ingestion |
|---|---|
| **Prosa prima del primo `#`** (front-matter) | Diventa il testo da cui l'LLM estrae **nome** e **descrizione** del corso. Non finisce in nessun modulo. |
| **`# Titolo`** (heading 1) | **Confine di modulo**: ogni `#` apre un nuovo modulo. Il **titolo del modulo** è il testo dell'`#` (l'eventuale `**grassetto**` viene rimosso dal titolo). |
| **`## Sezione` / `### Sotto-sezione`** | Sezioni **dentro** il modulo corrente (non creano moduli). |
| **`## Preparazione all'esame`** (o `ripasso` / `recap`) | Se presente come ultima sezione, viene **separata** dal modulo e salvata come `exam_prep_html` del corso. |
| Prosa, `**grassetto**`, `*corsivo*`, liste `-`/`1.`, tabelle | HTML standard nel `content` del modulo. |

> Regola d'oro: **un `#` = un modulo.** Usa `##`/`###` per le sezioni interne,
> non `#`. Metti titolo e introduzione del corso **prima** del primo `#`.

## Esempio minimo

```markdown
Corso SEGNALE — Fondamenta dell'AI operativa.
Un percorso pratico per usare l'AI in azienda ogni giorno, senza improvvisare.

# Il mondo che non aspetta

L'intelligenza artificiale generativa è uno strumento che impara i pattern del
linguaggio e li usa per rispondere, scrivere e analizzare.

## I limiti da conoscere

- **Allucinazioni**: può generare informazioni false con sicurezza.
- **Costo computazionale**: ogni richiesta consuma risorse.
- **Bias**: eredita i pregiudizi dei dati di addestramento.

# La tua azienda nell'AI

Dove l'AI genera valore concreto nei prossimi 90 giorni.

## Mappatura dei processi

Prosa della sezione...

## Preparazione all'esame

Domande di ripasso e punti chiave prima del quiz finale.
```

Risultato: **2 moduli** ("Il mondo che non aspetta", "La tua azienda nell'AI"),
con le rispettive sezioni; la prosa iniziale alimenta i metadati del corso; la
sezione finale "Preparazione all'esame" diventa `exam_prep_html`.

## Note

- Codifica file: **UTF-8** (accenti, em-dash, simboli resi correttamente).
- Il DOCX resta pienamente supportato: il formato viene scelto automaticamente
  dall'estensione del file caricato.
- L'esame (documento separato, opzionale) resta in DOCX.
