<?php

namespace App\Livewire;

use App\Models\NewsletterSubscription;
use Livewire\Component;

class NewsletterSubscribe extends Component
{
    public string $email = '';
    public bool $subscribed = false;

    protected function rules(): array
    {
        return [
            'email' => 'required|email|unique:newsletter_subscriptions,email',
        ];
    }

    protected function messages(): array
    {
        return [
            'email.required' => 'L\'email e obbligatoria.',
            'email.email' => 'Inserisci un indirizzo email valido.',
            'email.unique' => 'Questa email e gia iscritta.',
        ];
    }

    public function subscribe(): void
    {
        $this->validate();
        NewsletterSubscription::create(['email' => $this->email]);
        $this->subscribed = true;
    }

    public function render()
    {
        return view('livewire.newsletter-subscribe');
    }
}
