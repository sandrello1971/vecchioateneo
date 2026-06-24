@component('mail::message')
# Benvenuto/a in {{ $schoolName }}

Ciao {{ $teacher->name }},

la segreteria ti ha registrato come **docente**. Ecco le credenziali per il primo accesso:

- **Email:** {{ $teacher->email }}
- **Password temporanea:** {{ $tempPassword }}

Al primo accesso ti sarà chiesto di **impostare una nuova password**.

@component('mail::button', ['url' => $loginUrl])
Accedi e imposta la password
@endcomponent

Se non ti aspettavi questa email, ignorala.

Grazie,<br>
{{ $schoolName }}
@endcomponent
