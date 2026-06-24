<?php

namespace Tests\Feature\Schola;

use App\Models\ClassStudent;
use App\Models\DocumentRag;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingArtifact;
use App\Models\TeachingDocument;
use Database\Seeders\SubjectSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Schema fetta 1 Schola: vincoli DB (CHECK, unique), lineage fork e seed materie.
 * Ogni violazione di vincolo è l'ULTIMA operazione del test: in RefreshDatabase
 * (transazione per test) un'eccezione SQL aborta la transazione, quindi non si
 * eseguono query dopo.
 */
class Fetta1SchemaTest extends TestCase
{
    use RefreshDatabase;

    private function makeProfessor(): Student
    {
        return Student::create([
            'name' => 'Prof Rossi',
            'email' => 'prof+' . uniqid() . '@example.com',
            'password' => bcrypt('secret-pw'),
            'role' => 'professor',
            'is_active' => true,
        ]);
    }

    private function makeStudent(): Student
    {
        return Student::create([
            'name' => 'Studente',
            'email' => 'stud+' . uniqid() . '@example.com',
            'password' => bcrypt('secret-pw'),
            'role' => 'student',
            'is_active' => true,
        ]);
    }

    private function makeClass(Student $teacher): SchoolClass
    {
        $subject = Subject::firstOrCreate(['name' => 'Fisica'], ['is_custom' => false]);

        return SchoolClass::create([
            'teacher_id' => $teacher->id,
            'name' => '3ªB',
            'subject_id' => $subject->id,
            'school_year' => '2026/2027',
            'invite_code' => strtoupper(substr(md5(uniqid()), 0, 8)),
        ]);
    }

    public function test_role_check_accepts_professor(): void
    {
        $prof = $this->makeProfessor();

        $this->assertDatabaseHas('students', [
            'id' => $prof->id,
            'role' => 'professor',
        ]);
    }

    public function test_role_check_rejects_unknown_value(): void
    {
        $this->expectException(QueryException::class);

        Student::create([
            'name' => 'Ruolo Ignoto',
            'email' => 'x+' . uniqid() . '@example.com',
            'password' => bcrypt('secret-pw'),
            'role' => 'superuser', // non ammesso dal CHECK
            'is_active' => true,
        ]);
    }

    public function test_documents_rag_scope_accepts_class_and_rejects_invalid(): void
    {
        // scope valido: nessuna eccezione
        $ok = DocumentRag::create([
            'title' => 'Chunk classe',
            'content' => 'contenuto',
            'scope' => 'class',
        ]);
        $this->assertDatabaseHas('documents_rag', ['id' => $ok->id, 'scope' => 'class']);

        // scope non previsto: CHECK lo rifiuta (ultima operazione)
        $this->expectException(QueryException::class);
        DocumentRag::create([
            'title' => 'Chunk illegale',
            'content' => 'contenuto',
            'scope' => 'bogus',
        ]);
    }

    public function test_class_students_unique_enrollment(): void
    {
        $teacher = $this->makeProfessor();
        $class = $this->makeClass($teacher);
        $student = $this->makeStudent();

        ClassStudent::create([
            'school_class_id' => $class->id,
            'student_id' => $student->id,
            'status' => 'active',
        ]);

        // Seconda iscrizione stessa coppia → viola unique (ultima operazione)
        $this->expectException(QueryException::class);
        ClassStudent::create([
            'school_class_id' => $class->id,
            'student_id' => $student->id,
            'status' => 'pending',
        ]);
    }

    public function test_teaching_artifact_fork_lineage(): void
    {
        $teacher = $this->makeProfessor();
        $doc = TeachingDocument::create([
            'teacher_id' => $teacher->id,
            'title' => 'Lezione',
            'source_type' => 'text',
            'status' => 'ready',
            'extracted_text' => 'testo sorgente',
        ]);

        $origin = TeachingArtifact::create([
            'teaching_document_id' => $doc->id,
            'teacher_id' => $teacher->id,
            'type' => 'summary',
            'title' => 'Riassunto originale',
            'content' => 'contenuto originale',
            'status' => 'ready',
            'shared_with_teachers' => true,
        ]);

        // Fork: copia con origin_artifact_id valorizzato, SENZA documento sorgente
        $fork = TeachingArtifact::create([
            'teaching_document_id' => null,
            'teacher_id' => $this->makeProfessor()->id,
            'type' => 'summary',
            'title' => 'Riassunto forkato',
            'content' => 'contenuto originale',
            'status' => 'ready',
            'origin_artifact_id' => $origin->id,
        ]);

        // Modifica dell'originale: NON deve propagarsi al fork
        $origin->update(['content' => 'contenuto MODIFICATO', 'title' => 'Cambiato']);

        $fork->refresh();
        $this->assertSame($origin->id, $fork->origin_artifact_id, 'lineage preservato');
        $this->assertSame('contenuto originale', $fork->content, 'il fork è indipendente');
        $this->assertSame('Riassunto forkato', $fork->title);

        // Relazioni lineage
        $this->assertTrue($origin->forks->contains($fork));
        $this->assertSame($origin->id, $fork->origin->id);
    }

    public function test_subject_seeder_populates_subjects(): void
    {
        (new SubjectSeeder())->run();

        $this->assertGreaterThanOrEqual(30, Subject::count());
        $this->assertDatabaseHas('subjects', ['name' => 'Matematica', 'is_custom' => false]);
        $this->assertDatabaseHas('subjects', ['name' => 'Informatica', 'is_custom' => false]);

        // idempotenza: una seconda run non duplica
        $before = Subject::count();
        (new SubjectSeeder())->run();
        $this->assertSame($before, Subject::count());
    }
}
