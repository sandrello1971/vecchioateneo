<?php

namespace Tests\Feature\Freshness;

use App\Models\Course;
use App\Models\Module;
use App\Services\Freshness\StudentClaimExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * P25.B-a.2 — estrazione claim dal materiale studente (modules.content, HTML pulito):
 * ri-localizzazione VERBATIM nel content_html grezzo, regola "unico-nel-modulo o scarto".
 */
class StudentClaimExtractorTest extends TestCase
{
    use RefreshDatabase;

    private function course(): Course
    {
        return Course::create(['name' => 'INTERFERENZA', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
    }

    private function module(Course $course, string $html): Module
    {
        return Module::create(['course_id' => $course->id, 'title' => 'M', 'content' => $html, 'sort_order' => 0]);
    }

    private function fakeLlm(array $claims): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['claims' => $claims], JSON_UNESCAPED_UNICODE)]],
        ], 200)]);
    }

    public function test_estrae_e_rilocalizza_verbatim_su_html_pulito(): void
    {
        $course = $this->course();
        // HTML reale-like: frasi databili dentro singolo <p>, apostrofi tipografici curvi.
        $m = $this->module($course, '<p>Il mercato dell’intelligenza artificiale in Italia ha raggiunto 1,8 miliardi di euro, con una crescita del 58% in un solo anno. L’adozione nelle imprese italiane è raddoppiata.</p>');

        $this->fakeLlm([
            ['module_id' => $m->id, 'quote' => 'ha raggiunto 1,8 miliardi di euro', 'category' => 'prezzo'],
            // quote con apostrofo DRITTO → la normalizzazione deve ritrovarla; claim_text torna CURVO
            ['module_id' => $m->id, 'quote' => "L'adozione nelle imprese italiane", 'category' => 'data'],
            ['module_id' => $m->id, 'quote' => 'questa frase non esiste nel modulo', 'category' => 'data'],
            ['module_id' => '00000000-0000-0000-0000-000000000000', 'quote' => 'x', 'category' => 'data'],
        ]);

        $res = app(StudentClaimExtractor::class)->extract($course->modules()->get());

        $this->assertCount(2, $res['claims']);
        $this->assertCount(2, $res['rejected']);

        $c0 = $res['claims'][0];
        $this->assertSame($m->id, $c0['module_id']);
        $this->assertSame('prezzo', $c0['category']);
        $this->assertStringContainsString('1,8 miliardi di euro', $c0['claim_text']);
        $this->assertSame(0, $c0['sentence_ref']);

        // Normalizzazione: claim_text con apostrofo ORIGINALE (curvo), non quello dritto inviato.
        $this->assertStringContainsString('’', $res['claims'][1]['claim_text']);
        $this->assertSame(1, $res['claims'][1]['sentence_ref']);

        $reasons = implode(' | ', array_column($res['rejected'], 'reason'));
        $this->assertStringContainsString('non ritrovata', $reasons);
        $this->assertStringContainsString('inesistente', $reasons);
    }

    public function test_quote_non_unica_nel_modulo_scartata(): void
    {
        $course = $this->course();
        // La stessa frase compare DUE volte nel modulo → non univoca → scarto.
        $m = $this->module($course, '<p>Prezzo: 1,8 miliardi di euro.</p><p>Ribadito: 1,8 miliardi di euro.</p>');

        $this->fakeLlm([
            ['module_id' => $m->id, 'quote' => '1,8 miliardi di euro', 'category' => 'prezzo'],
        ]);

        $res = app(StudentClaimExtractor::class)->extract($course->modules()->get());

        $this->assertCount(0, $res['claims']);
        $this->assertCount(1, $res['rejected']);
        $this->assertStringContainsString('non unica', $res['rejected'][0]['reason']);
    }
}
