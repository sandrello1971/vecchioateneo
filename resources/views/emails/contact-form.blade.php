<h2>Nuovo messaggio — {{ atheneum_setting('instance_name', 'Atheneum') }}</h2>
<p><strong>Nome:</strong> {{ $contact->name }}</p>
<p><strong>Email:</strong> {{ $contact->email }}</p>
@if($contact->phone)<p><strong>Telefono:</strong> {{ $contact->phone }}</p>@endif
@if($contact->company)<p><strong>Azienda:</strong> {{ $contact->company }}</p>@endif
<p><strong>Messaggio:</strong></p>
<p>{{ $contact->message }}</p>
<hr>
<p><small>Inviato il {{ $contact->created_at->format('d/m/Y H:i') }} — IP {{ $contact->ip_address }}</small></p>
