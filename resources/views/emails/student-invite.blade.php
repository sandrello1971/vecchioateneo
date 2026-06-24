@component('mail::message')
# Benvenuto/a in {{ $schoolName }}

Ciao {{ $student->name }},

la tua scuola ti ha registrato. Ecco le credenziali per il primo accesso:

- **Email:** {{ $student->email }}
- **Password temporanea:** {{ $tempPassword }}

Al primo accesso ti sarà chiesto di **impostare una nuova password**.

@component('mail::button', ['url' => $loginUrl])
Accedi e imposta la password
@endcomponent

Se non ti aspettavi questa email, ignorala.

{{ $schoolName }}
@endcomponent
