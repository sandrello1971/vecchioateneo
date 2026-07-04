<?php

namespace Tests\Feature\Schola;

use App\Jobs\ExtractTeachingDocumentJob;
use App\Models\School;
use App\Models\Student;
use App\Models\TeachingDocument;
use App\Support\VideoAiConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SchoolMaterialTest extends TestCase
{
    use RefreshDatabase;

    private function school(): School
    {
        return School::create(['name' => 'Liceo', 'slug' => 'l-' . uniqid(), 'type' => 'liceo', 'status' => 'active']);
    }

    private function admin(School $school): Student
    {
        return Student::create([
            'name' => 'Segreteria', 'email' => 'adm+' . uniqid() . '@example.com',
            'password' => bcrypt('x'), 'role' => null, 'is_secretary' => true,
            'school_id' => $school->id, 'is_active' => true, 'must_change_password' => false,
        ]);
    }

    private function prof(School $school): Student
    {
        return Student::create([
            'name' => 'Prof', 'email' => 'prof+' . uniqid() . '@example.com',
            'password' => bcrypt('x'), 'role' => 'professor', 'school_id' => $school->id,
            'is_active' => true, 'must_change_password' => false,
        ]);
    }

    private function asAdmin(Student $a): self
    {
        return $this->withSession(['student_id' => $a->id, 'student_name' => $a->name, 'student_email' => $a->email]);
    }

    private function doc(Student $owner, array $attrs = []): TeachingDocument
    {
        return TeachingDocument::create(array_merge([
            'teacher_id' => $owner->id, 'title' => 'Materiale', 'source_type' => 'text',
            'school_id' => $owner->school_id, 'status' => 'ready', 'extracted_text' => 'x',
        ], $attrs));
    }

    public function test_admin_uploads_school_material(): void
    {
        Bus::fake();
        Storage::fake('local');
        $school = $this->school();
        $admin = $this->admin($school);

        $this->asAdmin($admin)->post(route('scuola.materiali.store'), [
            'title' => 'Materiale scuola', 'source_type' => 'text', 'text_content' => 'Contenuto',
        ])->assertRedirect(route('scuola.materiali.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('teaching_documents', [
            'teacher_id' => $admin->id, 'title' => 'Materiale scuola',
            'is_school_material' => true, 'school_id' => $school->id, 'share_scope' => 'all',
        ]);
        Bus::assertDispatchedAfterResponse(ExtractTeachingDocumentJob::class);
    }

    public function test_admin_sees_all_school_materials_and_can_delete(): void
    {
        $school = $this->school();
        $admin = $this->admin($school);
        $teacher = $this->prof($school);
        $doc = $this->doc($teacher, ['title' => 'Del docente']);

        $this->asAdmin($admin)->get(route('scuola.materiali.index'))->assertOk()->assertSee('Del docente');

        $this->asAdmin($admin)->delete(route('scuola.materiali.destroy', $doc))->assertRedirect();
        $this->assertSoftDeleted('teaching_documents', ['id' => $doc->id]);
    }

    public function test_admin_cannot_delete_material_of_other_school(): void
    {
        $schoolA = $this->school();
        $schoolB = $this->school();
        $teacherA = $this->prof($schoolA);
        $docA = $this->doc($teacherA);
        $adminB = $this->admin($schoolB);

        $this->asAdmin($adminB)->delete(route('scuola.materiali.destroy', $docA))->assertForbidden();
        $this->assertDatabaseHas('teaching_documents', ['id' => $docA->id, 'deleted_at' => null]);
    }

    public function test_school_material_visible_in_library_to_school_teacher(): void
    {
        $school = $this->school();
        $admin = $this->admin($school);
        $mat = $this->doc($admin, ['is_school_material' => true, 'share_scope' => 'all']);

        $teacher = $this->prof($school);
        $this->assertTrue(TeachingDocument::visibleAsSharedTo($teacher)->whereKey($mat->id)->exists());

        // Docente di altra scuola: non lo vede.
        $other = $this->prof($this->school());
        $this->assertFalse(TeachingDocument::visibleAsSharedTo($other)->whereKey($mat->id)->exists());
    }

    public function test_signing_video_ai_dpa_unblocks_media_uploads(): void
    {
        $school = $this->school();
        $admin = $this->admin($school);
        $prof = $this->prof($school);

        // Senza DPA video-AI: audio bloccato.
        $this->assertTrue(VideoAiConsent::blocked($prof, 'audio'));

        $this->asAdmin($admin)->post(route('scuola.privacy.video-dpa'))->assertRedirect();
        $this->assertTrue($school->fresh()->hasVideoAiDpa());

        // Firmato: audio consentito; ma il DPA generale resta indipendente.
        $this->assertFalse(VideoAiConsent::blocked($prof->fresh(), 'audio'));
        $this->assertNull($school->fresh()->dpa_signed_at);
    }
}
