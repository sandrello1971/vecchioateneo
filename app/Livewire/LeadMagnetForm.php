<?php

namespace App\Livewire;

use App\Mail\LeadMagnetMail;
use App\Models\Lead;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Url;
use Livewire\Component;

class LeadMagnetForm extends Component
{
    // Campi visibili del form
    public string $name = '';
    public string $email = '';
    public string $company = '';
    public bool $privacy_accepted = false;

    // Honeypot: invisibile a utenti, riempito dai bot
    public string $website = '';

    // UTM: auto-capture dalla querystring via Livewire #[Url]
    #[Url(as: 'utm_source')]
    public ?string $utmSource = null;

    #[Url(as: 'utm_medium')]
    public ?string $utmMedium = null;

    #[Url(as: 'utm_campaign')]
    public ?string $utmCampaign = null;

    #[Url(as: 'utm_content')]
    public ?string $utmContent = null;

    #[Url(as: 'utm_term')]
    public ?string $utmTerm = null;

    protected function rules(): array
    {
        return [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email|max:255',
            'company' => 'required|min:2|max:150',
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
            'email.max' => 'L\'email non puo superare 255 caratteri.',
            'company.required' => 'L\'azienda e obbligatoria.',
            'company.min' => 'Il nome azienda deve avere almeno 2 caratteri.',
            'company.max' => 'Il nome azienda non puo superare 150 caratteri.',
            'privacy_accepted.accepted' => 'Devi accettare la privacy policy.',
        ];
    }

    public function submit()
    {
        // Honeypot: bot trapped. Risposta silenziosa con redirect normale,
        // così il bot non capisce che è stato intercettato.
        if (filled($this->website)) {
            Log::info('Honeypot triggered su /mappa-percorso', [
                'ip' => request()->ip(),
                'ua' => request()->userAgent(),
            ]);
            return redirect()->route('lead-magnet.thank-you');
        }

        $this->validate();

        // Rate limiting: max 3 submit/IP/10min, pattern allineato a ContactForm
        $key = 'lead-magnet:' . request()->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            $this->addError('email', "Troppi tentativi. Riprova tra {$seconds} secondi.");
            return null;
        }
        RateLimiter::hit($key, 600);

        $lead = Lead::create([
            'email' => strtolower(trim($this->email)),
            'name' => trim($this->name),
            'company' => trim($this->company),
            'source' => 'mappa-percorso-pdf',
            'utm_source' => $this->utmSource,
            'utm_medium' => $this->utmMedium,
            'utm_campaign' => $this->utmCampaign,
            'utm_content' => $this->utmContent,
            'utm_term' => $this->utmTerm,
            'ip_address' => request()->ip(),
            'user_agent' => substr(request()->userAgent() ?? '', 0, 500),
            'privacy_accepted' => true,
            'privacy_accepted_at' => now(),
        ]);

        // Invio email con PDF allegato.
        // Path del PDF a runtime — non versionato, vive solo nel deploy.
        $pdfPath = storage_path('app/lead-magnets/AI_Ratio_Mappa_Percorso.pdf');

        try {
            if (!file_exists($pdfPath)) {
                throw new \RuntimeException("PDF non trovato: {$pdfPath}");
            }

            Mail::to($lead->email)->send(new LeadMagnetMail($lead, $pdfPath));

            $lead->update([
                'email_sent' => true,
                'email_sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Lead magnet: invio email fallito', [
                'lead_id' => $lead->id,
                'message' => $e->getMessage(),
            ]);
            $lead->update(['email_error' => substr($e->getMessage(), 0, 1000)]);
            // Comportamento volutamente non-bloccante: il lead è salvato,
            // l'utente vede la thank you, possiamo fare retry manuale dopo.
        }

        return redirect()->route('lead-magnet.thank-you');
    }

    public function render()
    {
        return view('livewire.lead-magnet-form');
    }
}
