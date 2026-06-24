<?php

namespace App\Livewire;

use App\Mail\ContactFormMessage;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class ContactForm extends Component
{
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $company = '';
    public string $message = '';
    public bool $privacy_accepted = false;
    public bool $sent = false;

    protected function rules(): array
    {
        return [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email|max:255',
            'phone' => ['nullable', 'regex:/^(\+39)?[\s\-]?[0-9]{2,4}[\s\-]?[0-9]{5,8}$/'],
            'company' => 'nullable|max:100',
            'message' => 'required|min:10|max:2000',
            'privacy_accepted' => 'accepted',
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Il nome e obbligatorio.',
            'name.min' => 'Il nome deve avere almeno 2 caratteri.',
            'name.max' => 'Il nome non puo superare 100 caratteri.',
            'email.required' => 'L\'email e obbligatoria.',
            'email.email' => 'Inserisci un indirizzo email valido.',
            'phone.regex' => 'Inserisci un numero di telefono valido.',
            'company.max' => 'Il nome azienda non puo superare 100 caratteri.',
            'message.required' => 'Il messaggio e obbligatorio.',
            'message.min' => 'Il messaggio deve avere almeno 10 caratteri.',
            'message.max' => 'Il messaggio non puo superare 2000 caratteri.',
            'privacy_accepted.accepted' => 'Devi accettare la privacy policy.',
        ];
    }

    public function submit(): void
    {
        $this->validate();

        $key = 'contact-form:' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            $this->addError('message', "Troppi tentativi. Riprova tra {$seconds} secondi.");
            return;
        }
        RateLimiter::hit($key, 600);

        $contact = ContactMessage::create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'company' => $this->company ?: null,
            'message' => $this->message,
            'privacy_accepted' => $this->privacy_accepted,
            'ip_address' => request()->ip(),
        ]);

        $contactEmail = atheneum_setting('contact_email', config('mail.from.address'));
        Mail::to($contactEmail)->send(new ContactFormMessage($contact));

        $this->reset(['name', 'email', 'phone', 'company', 'message', 'privacy_accepted']);
        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.contact-form');
    }
}
