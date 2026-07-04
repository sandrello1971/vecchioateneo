<?php

namespace Tests\Feature\Schola;

use App\Jobs\IngestMaterialSharedJob;
use App\Models\DocumentRag;
use App\Models\School;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingArtifact;
use App\Models\TeachingDocument;
use App\Services\RagService;
use App\Services\Schola\ArtifactRagIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MaterialSharingTest extends TestCase
{
    use RefreshDatabase;

    private function prof(?School $school = null): Student
    {
        return Student::create([
            'name' => 'Prof', 'email' => 'prof+' . uniqid() . '@example.com',
            'password' => bcrypt('x'), 'role' => 'professor', 'school_id' => $school?->id,
            'is_active' => true, 'must_change_password' => false,
        ]);
    }

    private function asProf(Student $p): self
    {
        return $this->withSession(['student_id' => $p->id, 'student_name' => $p->name, 'student_email' => $p->email]);
    }

    private function school(): School
    {
        return School::create(['name' => 'Liceo', 'slug' => 'l-' . uniqid(), 'type' => 'liceo', 'status' => 'active']);
    }

    private function subject(): Subject
    {
        return Subject::create(['name' => 'Storia ' . uniqid(), 'is_custom' => true]);
    }

    private function cattedra(Student $p, Subject $s, School $school): void
    {
        $p->teachableSubjects()->attach($s->id, ['school_id' => $school->id]);
    }

    private function makeReadyDoc(Student $p, ?Subject $subject = null, string $type = 'text', string $content = 'Il moto rettilineo uniforme.'): TeachingDocument
    {
        $doc = TeachingDocument::create([
            'teacher_id' => $p->id, 'title' => 'Materiale', 'source_type' => $type,
            'subject_id' => $subject?->id, 'school_id' => $p->school_id,
            'status' => 'ready', 'extracted_text' => $content,
        ]);
        TeachingArtifact::create([
            'teaching_document_id' => $doc->id, 'teacher_id' => $p->id, 'type' => 'transcript',
            'title' => 'Trascrizione', 'content' => $content, 'subject_id' => $subject?->id, 'status' => 'ready',
        ]);

        return $doc;
    }

    public function test_share_all_is_visible_only_within_the_same_school(): void
    {
        Bus::fake();
        $school = $this->school();
        $owner = $this->prof($school);
        $doc = $this->makeReadyDoc($owner);

        $this->asProf($owner)->patch(route('docente.materials.sharing', $doc), [
            'scope' => 'all', 'rights_ack' => 1,
        ])->assertRedirect();

        $doc->refresh();
        $this->assertSame('all', $doc->share_scope);
        $this->assertSame($school->id, $doc->school_id);

        $sameSchool = $this->prof($school);
        $this->assertTrue(TeachingDocument::visibleAsSharedTo($sameSchool)->whereKey($doc->id)->exists());

        $otherSchool = $this->prof($this->school());
        $this->assertFalse(TeachingDocument::visibleAsSharedTo($otherSchool)->whereKey($doc->id)->exists());

        // il proprietario non vede il proprio tra i condivisi.
        $this->assertFalse(TeachingDocument::visibleAsSharedTo($owner)->whereKey($doc->id)->exists());
    }

    public function test_share_subject_visible_only_same_subject_and_school(): void
    {
        Bus::fake();
        $school = $this->school();
        $subject = $this->subject();
        $owner = $this->prof($school);
        $this->cattedra($owner, $subject, $school);
        $doc = $this->makeReadyDoc($owner, $subject);

        $this->asProf($owner)->patch(route('docente.materials.sharing', $doc), [
            'scope' => 'subject', 'rights_ack' => 1,
        ])->assertRedirect();
        $doc->refresh();
        $this->assertSame('subject', $doc->share_scope);
        $this->assertSame($school->id, $doc->school_id);

        // Stessa materia, stessa scuola → vede.
        $same = $this->prof($school);
        $this->cattedra($same, $subject, $school);
        $this->assertTrue(TeachingDocument::visibleAsSharedTo($same)->whereKey($doc->id)->exists());

        // Stessa materia, scuola diversa → NON vede.
        $school2 = $this->school();
        $otherSchool = $this->prof($school2);
        $this->cattedra($otherSchool, $subject, $school2);
        $this->assertFalse(TeachingDocument::visibleAsSharedTo($otherSchool)->whereKey($doc->id)->exists());
    }

    public function test_schoolless_teacher_cannot_share(): void
    {
        Bus::fake();
        $owner = $this->prof(); // nessuna scuola
        $doc = $this->makeReadyDoc($owner);

        $this->asProf($owner)->patch(route('docente.materials.sharing', $doc), [
            'scope' => 'all', 'rights_ack' => 1,
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertNull($doc->fresh()->share_scope);
    }

    public function test_pdf_material_is_not_shareable(): void
    {
        Bus::fake();
        $school = $this->school();
        $owner = $this->prof($school);
        $doc = $this->makeReadyDoc($owner, null, 'pdf');

        $this->asProf($owner)->patch(route('docente.materials.sharing', $doc), [
            'scope' => 'all', 'rights_ack' => 1,
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertNull($doc->fresh()->share_scope);
    }

    public function test_first_share_requires_rights_ack(): void
    {
        Bus::fake();
        $school = $this->school();
        $owner = $this->prof($school);
        $doc = $this->makeReadyDoc($owner);

        $this->asProf($owner)->patch(route('docente.materials.sharing', $doc), [
            'scope' => 'all', // niente rights_ack
        ])->assertRedirect()->assertSessionHas('needs_ack', true);

        $this->assertNull($doc->fresh()->share_scope);
        $this->assertNull($owner->fresh()->library_rights_ack_at);
    }

    public function test_import_creates_independent_copy_in_pool(): void
    {
        Bus::fake();
        $school = $this->school();
        $owner = $this->prof($school);
        $doc = $this->makeReadyDoc($owner);
        $doc->update(['share_scope' => 'all']);

        $other = $this->prof($school);
        $this->asProf($other)->post(route('docente.materials.shared.import', $doc))->assertRedirect();

        $copy = TeachingDocument::where('teacher_id', $other->id)->firstOrFail();
        $this->assertNull($copy->lesson_id);
        $this->assertNull($copy->share_scope);
        $this->assertSame('ready', $copy->status);
        $this->assertDatabaseHas('teaching_artifacts', [
            'teaching_document_id' => $copy->id, 'teacher_id' => $other->id, 'type' => 'transcript',
        ]);
    }

    public function test_shared_material_is_rag_indexed_and_retrievable_then_purged(): void
    {
        Http::fake(['*/api/embeddings' => Http::response([
            'embeddings' => [array_fill(0, 768, 0.01)], 'model' => 'm', 'dimensions' => 768,
        ], 200)]);

        $school = $this->school();
        $owner = $this->prof($school);
        $doc = $this->makeReadyDoc($owner, null, 'text', 'Il moto rettilineo uniforme e la velocita costante.');
        $doc->update(['share_scope' => 'all']);

        (new IngestMaterialSharedJob($doc->id))->handle(app(ArtifactRagIngestor::class));

        $this->assertTrue(
            DocumentRag::where('scope', 'teacher_shared')->where('metadata->document_id', $doc->id)->exists()
        );

        // Un docente della STESSA scuola lo trova via retrieval.
        $same = $this->prof($school);
        $docs = app(RagService::class)->searchClassScoped('moto rettilineo', [], $same->id, 5);
        $this->assertTrue($docs->isNotEmpty(), 'Il docente della scuola dovrebbe pescare il materiale condiviso');

        // Un docente di ALTRA scuola NON lo pesca.
        $other = $this->prof($this->school());
        $otherDocs = app(RagService::class)->searchClassScoped('moto rettilineo', [], $other->id, 5);
        $this->assertTrue($otherDocs->isEmpty(), 'Un docente di altra scuola non deve pescare il condiviso di scuola');

        // Unshare → rimozione dei chunk condivisi.
        $doc->update(['share_scope' => null]);
        app(ArtifactRagIngestor::class)->purgeTeacherShared($doc->id);
        $this->assertFalse(
            DocumentRag::where('scope', 'teacher_shared')->where('metadata->document_id', $doc->id)->exists()
        );
    }
}
