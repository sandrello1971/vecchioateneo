<?php

namespace App\Providers;

use App\Http\Middleware\StudentBroadcastAuth;
use App\Models\Certificate;
use App\Models\Conversation;
use App\Models\Setting;
use App\Models\Student;
use App\Observers\CertificateObserver;
use App\Policies\ConversationPolicy;
use App\Support\ExamState;
use App\Support\StudentCourseAccess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // V3 — TTS parametrico: il provider concreto è scelto da config (TTS_PROVIDER).
        $this->app->bind(\App\Services\Tts\TtsProvider::class, function ($app) {
            $provider = config('services.tts.provider', 'elevenlabs');
            $class = config("services.tts.providers.{$provider}");
            if (!$class || !class_exists($class)) {
                throw new \RuntimeException("Provider TTS sconosciuto: {$provider}");
            }

            return $app->make($class);
        });
    }

    public function boot(): void
    {
        $this->applyMailSettingsOverride();

        // Transport Brevo via API HTTP (riusa la BREVO_API_KEY, come l'assessment).
        // Permette MAIL_MAILER=brevo senza credenziali SMTP dedicate.
        Mail::extend('brevo', function (array $config) {
            return new BrevoApiTransport((string) ($config['key'] ?? config('services.brevo.key')));
        });

        Certificate::observe(CertificateObserver::class);

        Gate::policy(Conversation::class, ConversationPolicy::class);

        // Password policy unificata per studenti + admin + reset.
        // Prod: 12 char min + mixed case + numeri + simboli + check uncompromised
        //       (haveibeenpwned API, gratis, no API key).
        // Dev:  8 char min (test-friendly, no network call).
        Password::defaults(function () {
            return app()->isProduction()
                ? Password::min(12)->letters()->mixedCase()->numbers()->symbols()->uncompromised()
                : Password::min(8);
        });

        // Broadcasting: registra /broadcasting/auth con il nostro middleware
        // session-based (Officina non usa guard Laravel), poi carica channels.php.
        Broadcast::routes(['middleware' => ['web', StudentBroadcastAuth::class]]);
        require base_path('routes/channels.php');

        // Rate limiter per la verifica pubblica del certificato. Per-IP esplicito,
        // così il budget non è condiviso tra utenti diversi dietro la stessa rotta.
        RateLimiter::for('certificate-verify', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // Login: 5/min per email|IP (anti brute-force standard).
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by(strtolower($request->input('email') ?? '') . '|' . $request->ip())
                ->response(function () {
                    return response('Troppi tentativi di login. Riprova tra un minuto.', 429);
                });
        });

        // Password reset: 3/min per IP (anti email enumeration).
        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        // Minerva chat: 20/min per studente (Claude API cost-control).
        RateLimiter::for('minerva-chat', function (Request $request) {
            return Limit::perMinute(20)
                ->by(session('student_id') ?? $request->ip())
                ->response(function () {
                    return response()->json([
                        'error' => 'Stai inviando troppi messaggi. Attendi un minuto e riprova.',
                    ], 429);
                });
        });

        // Generazione/ingestion AI Schola: 8/min per utente|IP (oltre al tetto
        // GIORNALIERO §8.2). Protegge gli endpoint che dispatchano lavoro AI
        // (auto-generazione studente, generazione/rigenerazione artefatti,
        // upload/estrazione materiali).
        RateLimiter::for('schola-generate', function (Request $request) {
            return Limit::perMinute(8)
                ->by((session('student_id') ?? '') . '|' . $request->ip())
                ->response(function () {
                    return back()->with('error', 'Troppe richieste di generazione. Attendi un minuto e riprova.');
                });
        });

        // API default: 60/min per utente (protezione generica).
        RateLimiter::for('api-default', function (Request $request) {
            return Limit::perMinute(60)->by(session('student_id') ?? session('admin_email') ?? $request->ip());
        });

        // Join classe con codice: 8/min per studente|IP (anti brute-force codici invito).
        RateLimiter::for('class-join', function (Request $request) {
            return Limit::perMinute(8)
                ->by((session('student_id') ?? '') . '|' . $request->ip())
                ->response(function () {
                    return back()->withErrors([
                        'invite_code' => 'Troppi tentativi. Riprova tra un minuto.',
                    ])->withInput();
                });
        });

        $this->shareInstanceName();

        // Branding per scuola (fase 2): risolto sopra il default piattaforma e
        // condiviso con i layout segreteria e docente. Utenti "liberi"
        // (school_id NULL) → branding piattaforma invariato.
        View::composer(['layouts.scuola', 'layouts.docente'], function ($view) {
            $studentId = session('student_id');
            $student = $studentId ? Student::find($studentId) : null;
            $school = $student?->school_id ? \App\Models\School::find($student->school_id) : null;
            $view->with('branding', \App\Services\Schola\SchoolBranding::for($school));
        });

        // Identità multi-contesto: capacità dell'account per lo switch di
        // contesto (un account può essere corsista + professore + segreteria).
        View::composer(['layouts.scuola', 'layouts.docente', 'layouts.student'], function ($view) {
            $studentId = session('student_id');
            $s = $studentId ? Student::find($studentId) : null;
            $view->with('identity', [
                'professor' => (bool) $s?->isProfessor(),
                'secretary' => (bool) $s?->isSecretary(),
                'courses' => (bool) $s?->hasCourseAccess(),
            ]);
        });

        View::composer('layouts.student', function ($view) {
            $studentId = session('student_id');
            $student = $studentId ? Student::find($studentId) : null;

            $sidebarCourses = $student
                ? app(StudentCourseAccess::class)->navigableCourses($student)
                : collect();

            $examLock = $studentId
                ? app(ExamState::class)->hasActiveExam($studentId)
                : false;

            // Unread DM messages count: messaggi non letti nelle conversation dove
            // l'utente è partecipante (sia come studente sia come formatore),
            // escludendo quelli inviati da lui stesso.
            $unreadMessages = $student
                ? \App\Models\Message::query()
                    ->whereNull('read_at')
                    ->where('sender_id', '!=', $student->id)
                    ->whereIn('conversation_id', \App\Models\Conversation::query()
                        ->where(function ($q) use ($student) {
                            $q->where('student_id', $student->id)
                              ->orWhere('instructor_id', $student->id);
                        })
                        ->pluck('id'))
                    ->count()
                : 0;

            // Unread announcements: annunci dei corsi a cui l'utente e' iscritto
            // attivo, MENO quelli che ha gia' letto (announcement_reads pivot).
            // Esclude gli annunci che l'utente stesso ha pubblicato.
            $unreadAnnouncements = 0;
            if ($student) {
                $enrolledCourseIds = \DB::table('student_course')
                    ->where('student_id', $student->id)
                    ->where('is_active', true)
                    ->pluck('course_id');

                if ($enrolledCourseIds->isNotEmpty()) {
                    $unreadAnnouncements = \App\Models\Announcement::query()
                        ->whereIn('course_id', $enrolledCourseIds)
                        ->where('instructor_id', '!=', $student->id)
                        ->whereNotExists(function ($q) use ($student) {
                            $q->select(\DB::raw(1))
                              ->from('announcement_reads')
                              ->whereColumn('announcement_id', 'announcements.id')
                              ->where('student_id', $student->id);
                        })
                        ->count();
                }
            }

            $view->with([
                'sidebarStudent'        => $student,
                'sidebarCourses'        => $sidebarCourses,
                'examLock'              => $examLock,
                'unreadMessages'        => $unreadMessages,
                'unreadAnnouncements'   => $unreadAnnouncements,
            ]);
        });
    }

    /**
     * Override runtime della config mail dal settings store, SOLO se le
     * chiavi sono valorizzate. Difensivo: chiavi vuote → nessuna modifica
     * → si continua a usare .env (no regressione produzione).
     */
    private function applyMailSettingsOverride(): void
    {
        $host = Setting::resolve('mail_host');
        if (!$host) {
            return; // se host non c'è, non tocchiamo nulla
        }

        $overrides = [
            'mail.mailers.smtp.host'       => $host,
            'mail.mailers.smtp.port'       => Setting::resolve('mail_port', 587),
            'mail.mailers.smtp.username'   => Setting::resolve('mail_username'),
            'mail.mailers.smtp.encryption' => Setting::resolve('mail_encryption', 'tls') ?: null,
        ];

        $fromAddress = Setting::resolve('mail_from_address');
        $fromName    = Setting::resolve('mail_from_name');
        if ($fromAddress) $overrides['mail.from.address'] = $fromAddress;
        if ($fromName)    $overrides['mail.from.name']    = $fromName;

        $encPwd = Setting::resolve('mail_password_encrypted');
        if ($encPwd) {
            try {
                $overrides['mail.mailers.smtp.password'] = Crypt::decryptString($encPwd);
            } catch (\Throwable $e) {
                // Password cifrata illeggibile → preferisco NON sovrascrivere
                // password .env piuttosto che disabilitare la mail in prod.
            }
        }

        Config::set($overrides);
    }

    private function shareInstanceName(): void
    {
        $instanceName = Setting::resolve('instance_name', 'Atheneum');
        View::share('instanceName', $instanceName);
    }
}
