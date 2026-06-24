@component('mail::message')
# Ciao {{ $student->name }}!

Sono passati alcuni giorni dall'ultima volta che hai visitato **{{ atheneum_setting('instance_name', 'Atheneum') }}**.

Il tuo percorso formativo ti aspetta. Ogni modulo che completi è un passo concreto verso una gestione più efficace dell'AI nella tua azienda.

@component('mail::button', ['url' => $dashboardUrl, 'color' => 'success'])
Riprendi il tuo percorso →
@endcomponent

Hai domande sui contenuti? Il chatbot **{{ atheneum_setting('assistant_name', 'Minerva') }}** è sempre disponibile nell'area studenti.

*{{ atheneum_setting('platform_tagline', 'Il Rumore Che Serve') }}*

**Team {{ atheneum_setting('platform_owner', 'Noscite') }}**
@endcomponent
