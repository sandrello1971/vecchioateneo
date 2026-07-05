# noscite-videoai — modifiche applicate SOLO sul servizio live

Il servizio `noscite-videoai` (`/var/www/noscite-monorepo/noscite-videoai`, systemd
`noscite-videoai`, user `noscite`, uvicorn su `127.0.0.1:8001`) **non è un repository
git**. Alcune funzionalità Schola di atheneum dipendono da modifiche applicate
direttamente ai file del servizio in produzione: vanno **riportate a mano** al prossimo
deploy/versionamento di videoai, altrimenti la ricerca in-video e il RAG dei video si
rompono (404 / risultati vuoti).

Aggiornato: 2026-07-05.

## 1. `POST /api/videos/{video_id}/index_chunks` — `backend/api/main.py`
Indicizza chunk di testo GIÀ PRONTI (copione + testo-slide) per un video **generato**,
senza upload né trascrizione. Idempotente (reset della collection ChromaDB prima di
reindicizzare) + upsert nel DB SQLite così `/api/search` lo elenca. Auth interna globale
(`require_internal_token`). Chiamato da atheneum `VideoIndexService::indexGenerated`.
Senza questo endpoint la pubblicazione del video narrato dà **404** e la ricerca è vuota.

## 2. `GET /api/videos/{video_id}/chunks_text` — `backend/api/main.py`
Read-only: ritorna TUTTI i chunk testuali del video (parlato + frame/Vision), ordinati
per `start`. Alimenta il RAG della Minerva per i **video caricati** dal docente
(atheneum `VideoAIService::getChunksText` → `IngestUploadedVideoJob`). Video assente/
senza indice → `{"chunks": []}`.

## 3. Ricerca IN-VIDEO ibrida — `backend/chat/engine.py` + `backend/rag/embedder.py`
`search_across_videos` è passata da retrieval **solo semantico top-3** a **ibrido**:
- semantico top-8 (soglia distanza < 0.75) **+**
- scansione **lessicale** su TUTTI i chunk (nuovo metodo `VideoIndex.all_chunks()` in
  `embedder.py`): i chunk che contengono letteralmente i termini cercati salgono in cima
  (boost sulle frasi "parola numero", es. "articolo 4").
- `_salient_terms()` scarta le parole di contorno/stopword ("cercami dove si parla di…")
  che diluivano l'embedding e impedivano di pescare il termine esatto.

Motivo: una query tipo *"cercami dove si parla di articolo 4"* non finiva nei top-3
semantici → risultati sbagliati. NB: se il termine non è indicizzato (il video non ne
parla) non c'è nulla da trovare; per i video **generati** i frame sono solo testo-slide,
per i **caricati** c'è la Vision.

## Come riportare
Le firme/funzioni da (ri)portare: `index_video_chunks`, `get_chunks_text` (main.py),
`search_across_videos`, `_salient_terms`, `_keyword_score` (chat/engine.py),
`VideoIndex.all_chunks` (rag/embedder.py). Dopo il porting: `systemctl restart
noscite-videoai` e verificare `/api/search` (match lessicali in cima) + `GET
/api/videos/{id}/chunks_text` (200).
