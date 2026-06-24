<x-mail::message>
# Benvenuto in {{ atheneum_setting('instance_name', 'Atheneum') }}, {{ $student->name }}!

Il tuo account studente e stato attivato. Puoi accedere all'area riservata e iniziare il tuo percorso formativo.

@if(count($courseNames) > 0)
## Corsi assegnati
@foreach($courseNames as $c)
- {{ $c }}
@endforeach
@endif

## Le tue credenziali

- **URL:** {{ $loginUrl }}
- **Email:** {{ $student->email }}

La tua **password temporanea** (selezionala e copiala per intero):

<x-mail::panel>
{{ $tempPassword }}
</x-mail::panel>

<x-mail::button :url="$loginUrl">
Accedi ad {{ atheneum_setting('instance_name', 'Atheneum') }}
</x-mail::button>

> Al primo accesso ti verra chiesto di impostare una password personale.
> Conserva questa email in un luogo sicuro fino a quando non avrai completato il primo accesso.

@php $supportEmail = atheneum_setting('mail_from_address', 'info@noscite.it'); @endphp
Se hai domande, scrivi a [{{ $supportEmail }}](mailto:{{ $supportEmail }}).

Buon lavoro,<br>
**Il team {{ atheneum_setting('platform_owner', 'Noscite') }}**<br>
<small>{{ atheneum_setting('instance_name', 'Atheneum') }} — {{ atheneum_setting('platform_tagline', 'Il Rumore Che Serve') }}</small>
</x-mail::message>
