<x-mail::message>
# Ciao {{ $lead->name }},

grazie per aver scaricato la **Mappa Percorso AI**.

In allegato trovi il PDF con il decision tree per orientarti tra i nostri percorsi formativi (RUMORE DI FONDO, INTERFERENZA, SEGNALE) e capire da dove partire — calibrato sui profili professionali e aggiornato all'AI Act e alla Legge 132/2025.

Se dopo averla letta vuoi parlarne con noi (capire dubbi, fare domande sul percorso giusto per {{ $lead->company }}, o esplorare opzioni custom per il tuo team), basta rispondere a questa email oppure usare il modulo contatti del sito.

<x-mail::button :url="$contattiUrl">
Parliamone insieme
</x-mail::button>

A presto,
**Il team The Glitch World**

<x-mail::subcopy>
Hai ricevuto questa email perché hai compilato il modulo su {{ url('/mappa-percorso') }}. Se non riconosci la richiesta, ignora pure questa email — non sei iscritto a nessuna mailing list. Trattiamo i tuoi dati secondo la nostra [privacy policy]({{ route('privacy') }}).
</x-mail::subcopy>
</x-mail::message>
