@component('mail::message')
# 🎓 Congratulazioni, {{ $student->name }}!

Hai superato l'esame finale del corso **{{ $course->name }}** e ottenuto la certificazione:

> **{{ $certificate->certification_name }}**

**Codice del tuo certificato:**

<div style="font-family:monospace; font-size:1.4rem; font-weight:700; padding:16px 20px; background:#F5F7F7; border:1px solid #C8D0D0; border-radius:8px; text-align:center; letter-spacing:2px; color:#1A1F1F; margin:16px 0;">{{ $certificate->code }}</div>

Chiunque può verificare l'autenticità del tuo certificato a questo indirizzo:

[{{ $verifyUrl }}]({{ $verifyUrl }})

@component('mail::button', ['url' => $downloadUrl, 'color' => 'success'])
Scarica il certificato PDF
@endcomponent

Il PDF è disponibile dopo aver effettuato l'accesso ad {{ atheneum_setting('instance_name', 'Atheneum') }}.

*{{ atheneum_setting('platform_tagline', 'Il Rumore Che Serve') }}*

**Team {{ atheneum_setting('platform_owner', 'Noscite') }}**
@endcomponent
