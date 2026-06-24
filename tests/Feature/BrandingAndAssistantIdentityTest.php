<?php

namespace Tests\Feature;

use App\Http\Controllers\Student\ChatController;
use App\Models\Setting;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingAndAssistantIdentityTest extends TestCase
{
    use RefreshDatabase;

    // ============================================================
    // Settings & defaults
    // ============================================================

    public function test_default_values_when_settings_are_empty(): void
    {
        $this->assertSame('Atheneum', atheneum_setting('instance_name', 'Atheneum'));
        $this->assertSame('In digitālī nova virtūs', atheneum_setting('platform_tagline', 'In digitālī nova virtūs'));
        $this->assertSame('Noscite Srl', atheneum_setting('platform_owner', 'Noscite Srl'));
        $this->assertSame('Minerva', atheneum_setting('assistant_name', 'Minerva'));
        $this->assertSame(
            "l'assistente AI di formazione",
            atheneum_setting('assistant_role_label', "l'assistente AI di formazione")
        );
    }

    public function test_setting_falls_back_to_default_when_empty_string_stored(): void
    {
        // Caso critico Fase 1: svuotare un campo nel pannello deve
        // tornare al default cablato, NON propagare stringa vuota.
        atheneum_setting_put('instance_name', '');
        $this->assertSame('Atheneum', atheneum_setting('instance_name', 'Atheneum'));

        atheneum_setting_put('instance_name', null);
        $this->assertSame('Atheneum', atheneum_setting('instance_name', 'Atheneum'));
    }

    public function test_setting_put_then_resolve_returns_new_value(): void
    {
        atheneum_setting_put('instance_name', 'Accademia X');
        $this->assertSame('Accademia X', atheneum_setting('instance_name', 'Officina'));

        // cache bust su put: cambio + rilettura immediata vede il nuovo valore
        atheneum_setting_put('instance_name', 'Accademia Y');
        $this->assertSame('Accademia Y', atheneum_setting('instance_name', 'Officina'));
    }

    // ============================================================
    // View rendering — è il banco di prova del refactor delle viste
    // ============================================================

    public function test_admin_login_renders_custom_platform_name(): void
    {
        atheneum_setting_put('instance_name', 'Accademia Rossi');

        $html = $this->get('/admin/login')->getContent();

        $this->assertStringContainsString('Accademia Rossi', $html);
        // Non deve contenere 'Officina' come brand a schermo
        // (URL come /admin/login non c'entrano: cerco il marker visivo)
        $this->assertStringNotContainsString('Officina Admin', $html);
        $this->assertStringNotContainsString('Officina Noscite', $html);
    }

    public function test_admin_login_falls_back_to_default_when_setting_empty(): void
    {
        atheneum_setting_put('instance_name', '');

        $html = $this->get('/admin/login')->getContent();
        $this->assertStringContainsString('Atheneum Admin', $html);
    }

    // ============================================================
    // Minerva system prompt — identity from settings
    // ============================================================

    public function test_minerva_system_prompt_uses_settings_identity(): void
    {
        atheneum_setting_put('assistant_name', 'Sofia');
        atheneum_setting_put('assistant_role_label', 'tutor IA');
        atheneum_setting_put('instance_name', 'Accademia X');

        $ctrl = app(ChatController::class);
        $prompt = $ctrl->buildMinervaSystemPrompt(
            ['Corso Demo'], 'summary', false, ''
        );

        $this->assertStringStartsWith('Sei Sofia, tutor IA di Accademia X.', $prompt);
    }

    public function test_course_chat_system_prompt_uses_settings_identity(): void
    {
        atheneum_setting_put('assistant_name', 'Aria');
        atheneum_setting_put('assistant_role_label', 'assistente del corso');
        atheneum_setting_put('instance_name', 'Edu');

        $ctrl = app(ChatController::class);
        $prompt = $ctrl->buildCourseChatSystemPrompt('Storia', '');

        $this->assertStringStartsWith('Sei Aria, assistente del corso per il corso Storia di Edu.', $prompt);
    }

    public function test_minerva_system_prompt_uses_default_identity_when_settings_empty(): void
    {
        atheneum_setting_put('assistant_name', '');
        atheneum_setting_put('assistant_role_label', '');
        atheneum_setting_put('instance_name', '');

        $ctrl = app(ChatController::class);
        $prompt = $ctrl->buildMinervaSystemPrompt(
            ['Corso Demo'], 'summary', false, ''
        );

        $this->assertStringStartsWith(
            "Sei Minerva, l'assistente AI di formazione di Atheneum.",
            $prompt
        );
    }

    // ============================================================
    // GUARDRAIL: il refactor NON deve mangiare il blocco comportamento
    // ============================================================

    /**
     * Test critico. Cambia liberamente l'identità ma verifica che le
     * regole comportamentali del prompt CORRENTE siano ancora tutte
     * presenti. Se anche una sola manca → test rosso → refactor
     * sbagliato. È la rete che protegge da "degrado silenzioso".
     */
    public function test_minerva_system_prompt_preserves_behavior_block(): void
    {
        atheneum_setting_put('assistant_name', 'Pippo');
        atheneum_setting_put('instance_name', 'TestPlatform');

        $ctrl = app(ChatController::class);
        $prompt = $ctrl->buildMinervaSystemPrompt(
            ['Corso A', 'Corso B'], 'summary', false, ''
        );

        // Frasi-chiave estratte dal prompt corrente (comportamento).
        // Ogni assertion qui sotto deve passare — se cambia il prompt
        // toglierne una è una scelta DELIBERATA.
        $this->assertStringContainsString('Rispondi in italiano', $prompt);
        $this->assertStringContainsString('cita il titolo', $prompt);
        $this->assertStringContainsString('Non inventare', $prompt);
        $this->assertStringContainsString('[MM:SS]', $prompt);
        $this->assertStringContainsString('Rispondi in 2-3 frasi brevi', $prompt);
        $this->assertStringContainsString('Lo studente ha accesso', $prompt);
    }

    public function test_minerva_instructor_prompt_preserves_note_formatore_block(): void
    {
        atheneum_setting_put('assistant_name', 'Test');
        atheneum_setting_put('instance_name', 'X');

        $ctrl = app(ChatController::class);
        $prompt = $ctrl->buildMinervaSystemPrompt(
            ['Corso A'], 'summary', true, ''
        );

        // Il ramo instructor deve mantenere la sezione "Note per il formatore"
        $this->assertStringContainsString('Note per il formatore', $prompt);
        $this->assertStringContainsString('Manuale Formatore', $prompt);
        $this->assertStringContainsString('FORMATORE', $prompt);
        $this->assertStringContainsString('errori comuni', $prompt);
    }

    public function test_course_chat_system_prompt_preserves_behavior_block(): void
    {
        atheneum_setting_put('assistant_name', 'Test');
        atheneum_setting_put('instance_name', 'X');

        $ctrl = app(ChatController::class);
        $prompt = $ctrl->buildCourseChatSystemPrompt('Storia', '');

        $this->assertStringContainsString('Rispondi SEMPRE in italiano', $prompt);
        $this->assertStringContainsString('cita il titolo del documento', $prompt);
        $this->assertStringContainsString('[MM:SS]', $prompt);
        $this->assertStringContainsString('DOCUMENTI e sui VIDEO', $prompt);
    }

    public function test_course_chat_domain_context_is_settings_bound(): void
    {
        $ctrl = app(ChatController::class);

        // Senza domain context: fallback generico. 'PMI italiane' era un bias
        // hardcoded rimosso nel refactor (commit 8524c8c) e reso condizionale
        // alla setting assistant_domain_context.
        atheneum_setting_put('assistant_domain_context', '');
        $generic = $ctrl->buildCourseChatSystemPrompt('Storia', '');
        $this->assertStringContainsString('esempi pratici concreti', $generic);
        $this->assertStringNotContainsString('PMI italiane', $generic);

        // Con domain context valorizzato: deve propagare nel prompt.
        atheneum_setting_put('assistant_domain_context', 'le PMI italiane');
        $scoped = $ctrl->buildCourseChatSystemPrompt('Storia', '');
        $this->assertStringContainsString('esempi pratici legati a: le PMI italiane', $scoped);
        $this->assertStringNotContainsString('esempi pratici concreti', $scoped);
    }
}
