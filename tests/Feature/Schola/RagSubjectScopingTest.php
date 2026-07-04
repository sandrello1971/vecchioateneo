<?php

namespace Tests\Feature\Schola;

use App\Models\DocumentRag;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Services\RagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RagSubjectScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Percorso ILIKE deterministico (niente embedding/rete) per testare lo scope.
        atheneum_setting_put('rag_vector_enabled_schola', false);
    }

    private function classChunk(SchoolClass $class, ?Subject $subject, string $content): DocumentRag
    {
        return DocumentRag::create([
            'scope' => 'class',
            'school_class_id' => $class->id,
            'subject_id' => $subject?->id,
            'title' => 'Chunk',
            'content' => $content,
            'chunk_index' => 0,
            'metadata' => [],
        ]);
    }

    public function test_default_answers_are_scoped_to_the_subject(): void
    {
        $school = School::create(['name' => 'L', 'slug' => 's-' . uniqid(), 'type' => 'liceo', 'status' => 'active']);
        $class = SchoolClass::create([
            'school_id' => $school->id, 'name' => '3A', 'school_year' => '2026/2027',
            'invite_code' => SchoolClass::generateInviteCode(), 'invite_enabled' => false,
            'requires_approval' => false, 'is_archived' => false,
        ]);
        $fisica = Subject::create(['name' => 'Fisica ' . uniqid(), 'is_custom' => true]);
        $storia = Subject::create(['name' => 'Storia ' . uniqid(), 'is_custom' => true]);

        $chF = $this->classChunk($class, $fisica, 'La energia cinetica in fisica.');
        $chS = $this->classChunk($class, $storia, 'La energia delle rivoluzioni in storia.');
        $chNull = $this->classChunk($class, null, 'La energia come tema trasversale.');

        $rag = app(RagService::class);

        // Default: materia = Fisica → solo Fisica (+ il chunk non classificato come fallback).
        $ids = $rag->searchClassScoped('energia', [$class->id], null, 10, $fisica->id, false)->pluck('id')->all();
        $this->assertContains($chF->id, $ids);
        $this->assertContains($chNull->id, $ids);
        $this->assertNotContains($chS->id, $ids, 'La materia Storia non deve comparire nella risposta di Fisica');
    }

    public function test_connect_expands_across_subjects(): void
    {
        $school = School::create(['name' => 'L', 'slug' => 's-' . uniqid(), 'type' => 'liceo', 'status' => 'active']);
        $class = SchoolClass::create([
            'school_id' => $school->id, 'name' => '3A', 'school_year' => '2026/2027',
            'invite_code' => SchoolClass::generateInviteCode(), 'invite_enabled' => false,
            'requires_approval' => false, 'is_archived' => false,
        ]);
        $fisica = Subject::create(['name' => 'Fisica ' . uniqid(), 'is_custom' => true]);
        $storia = Subject::create(['name' => 'Storia ' . uniqid(), 'is_custom' => true]);

        $chF = $this->classChunk($class, $fisica, 'La energia cinetica in fisica.');
        $chS = $this->classChunk($class, $storia, 'La energia delle rivoluzioni in storia.');

        $rag = app(RagService::class);

        // connect=true: materia Fisica ignorata → entrano anche i chunk di Storia.
        $ids = $rag->searchClassScoped('energia', [$class->id], null, 10, $fisica->id, true)->pluck('id')->all();
        $this->assertContains($chF->id, $ids);
        $this->assertContains($chS->id, $ids, 'Con "Cerca collegamenti" devono comparire anche le altre materie');
    }
}
