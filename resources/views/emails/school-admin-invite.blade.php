@component('mail::message')
# Accesso segreteria — {{ $schoolName }}

Ciao {{ $admin->name }},

ecco le credenziali per accedere all'area di segreteria di **{{ $schoolName }}**:

- **Email:** {{ $admin->email }}
- **Password temporanea:** {{ $tempPassword }}

Al primo accesso ti sarà chiesto di **impostare una nuova password**.

@component('mail::button', ['url' => $loginUrl])
Accedi e imposta la password
@endcomponent

Se non ti aspettavi questa email, ignorala.

{{ $schoolName }}
@endcomponent
