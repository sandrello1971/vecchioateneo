<?php

namespace Tests\Feature\Schola;

use App\Models\ArtifactPublication;
use App\Models\ClassStudent;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingArtifact;
use App\Models\TeachingDocument;
use App\Models\UnansweredQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    private function prof(): Student
    {
        return Student::create(['name' => 'P', 'email' => 'p+' . uniqid() . '@e.it',
            'password' => bcrypt('x'), 'role' => 'professor', 'is_active' => true, 'must_change_password' => false]);
    }

    private function student(): Student
    {
        return Student::create(['name' => 'S', 'email' => 's+' . uniqid() . '@e.it',
            'password' => bcrypt('x'), 'role' => 'student', 'is_active' => true, 'must_change_password' => false]);
    }

    private function klass(Student $t): SchoolClass
    {
        $sub = Subject::firstOrCreate(['name' => 'Fisica']);
        return SchoolClass::create(['teacher_id' => $t->id, 'name' => '3B', 'subject_id' => $sub->id,
            'school_year' => '2026/2027', 'invite_code' => SchoolClass::generateInviteCode(),
            'invite_enabled' => true, 'requires_approval' => false, 'is_archived' => false]);
    }

    private function asProf(Student $p): self
    {
        return $this->withSession(['student_id' => $p->id, 'student_name' => $p->name, 'student_email' => $p->email]);
    }

    private function asStudent(Student $s): self
    {
        return $this->withSession(['student_id' => $s->id, 'student_name' => $s->name, 'student_email' => $s->email]);
    }

    // ===== IDOR docente↔docente: B non accede alle risorse di A =====

    public function test_idor_docente_cannot_touch_other_teacher_resources(): void
    {
        Bus::fake();
        $a = $this->prof(); $b = $this->prof();
        $class = $this->klass($a);
        $doc = TeachingDocument::create(['teacher_id' => $a->id, 'title' => 'D', 'source_type' => 'audio',
            'status' => 'ready', 'extracted_text' => 't', 'source_files' => ['x/a.mp3']]);
        $art = TeachingArtifact::create(['teaching_document_id' => $doc->id, 'teacher_id' => $a->id,
            'type' => 'summary', 'title' => 'A', 'content' => 'c', 'status' => 'ready']);
        $pub = ArtifactPublication::create(['teaching_artifact_id' => $art->id, 'school_class_id' => $class->id, 'published_at' => now()]);
        $enr = ClassStudent::create(['school_class_id' => $class->id, 'student_id' => $this->student()->id, 'status' => 'pending']);
        $uq = UnansweredQuestion::create(['school_class_id' => $class->id, 'question' => 'q', 'status' => 'open']);

        // GET (403/404 attesi)
        foreach ([
            ['get', route('docente.classes.show', $class)],
            ['get', route('docente.classes.activity', $class)],
            ['get', route('docente.classes.questions', $class)],
            ['get', route('docente.materials.show', $doc)],
            ['get', route('docente.materials.status', $doc)],
            ['get', route('docente.materials.download', [$doc, 0])],
            ['get', route('docente.artifacts.show', $art)],
            ['get', route('docente.artifacts.status', $art)],
            ['get', route('docente.artifacts.publications.status', $art)],
        ] as [$m, $url]) {
            $code = $this->asProf($b)->{$m}($url)->getStatusCode();
            $this->assertContains($code, [403, 404], "GET $url dovrebbe negare (got $code)");
        }

        // Mutazioni (403/404 attesi)
        $this->asProf($b)->patch(route('docente.classes.update', $class), ['name' => 'hack'])->assertForbidden();
        $this->asProf($b)->post(route('docente.classes.regenerate-code', $class))->assertForbidden();
        $this->asProf($b)->patch(route('docente.classes.roster.update', [$class, $enr]), ['action' => 'approve'])->assertForbidden();
        $this->asProf($b)->patch(route('docente.materials.update', $doc), ['title' => 'h'])->assertForbidden();
        $this->asProf($b)->delete(route('docente.materials.destroy', $doc))->assertForbidden();
        $this->asProf($b)->post(route('docente.materials.retry', $doc))->assertForbidden();
        $this->asProf($b)->patch(route('docente.artifacts.update', $art), ['title' => 'h'])->assertForbidden();
        $this->asProf($b)->delete(route('docente.artifacts.destroy', $art))->assertForbidden();
        $this->asProf($b)->post(route('docente.artifacts.regenerate', $art))->assertForbidden();
        $this->asProf($b)->post(route('docente.artifacts.generate', $doc), ['type' => 'summary'])->assertForbidden();
        $this->asProf($b)->patch(route('docente.artifacts.sharing', $art), ['shared' => '1'])->assertForbidden();
        $this->asProf($b)->post(route('docente.artifacts.publish', $art), ['class_ids' => [$class->id]])->assertForbidden();
        $this->asProf($b)->delete(route('docente.publications.destroy', $pub))->assertForbidden();
        $this->asProf($b)->patch(route('docente.questions.update', $uq), ['status' => 'addressed'])->assertForbidden();
        $this->asProf($b)->post(route('docente.classes.questions.bulk', $class), ['question_ids' => [$uq->id], 'status' => 'addressed'])->assertForbidden();
    }

    // ===== IDOR studente: pending/removed/stranger negati su ogni endpoint classe =====

    public function test_idor_student_non_active_denied_everywhere(): void
    {
        Bus::fake();
        $prof = $this->prof(); $class = $this->klass($prof);
        $doc = TeachingDocument::create(['teacher_id' => $prof->id, 'title' => 'D', 'source_type' => 'audio',
            'status' => 'ready', 'extracted_text' => 't', 'source_files' => ['x/a.mp3']]);
        $art = TeachingArtifact::create(['teaching_document_id' => $doc->id, 'teacher_id' => $prof->id,
            'type' => 'transcript', 'title' => 'T', 'content' => 'c', 'status' => 'ready']);
        $pub = ArtifactPublication::create(['teaching_artifact_id' => $art->id, 'school_class_id' => $class->id,
            'students_can_generate' => true, 'published_at' => now()]);

        $pending = $this->student(); ClassStudent::create(['school_class_id' => $class->id, 'student_id' => $pending->id, 'status' => 'pending']);
        $removed = $this->student(); ClassStudent::create(['school_class_id' => $class->id, 'student_id' => $removed->id, 'status' => 'removed']);
        $stranger = $this->student();

        foreach ([$pending, $removed, $stranger] as $u) {
            $this->asStudent($u)->get(route('student.classes.show', $class))->assertForbidden();
            $this->asStudent($u)->get(route('student.classes.artifact.show', [$class, $pub]))->assertForbidden();
            $this->asStudent($u)->get(route('student.classes.artifact.source', [$class, $pub]))->assertForbidden();
            $this->asStudent($u)->get(route('student.classes.minerva', $class))->assertForbidden();
            $this->asStudent($u)->post(route('student.classes.artifact.generate', [$class, $pub]), ['type' => 'mindmap'])->assertForbidden();
            $this->asStudent($u)->postJson(route('student.minerva.ask'), ['question' => 'q', 'school_class_id' => $class->id])->assertForbidden();
        }
    }

    // ===== XSS: il markdown degli artefatti è sanitizzato =====

    public function test_artifact_markdown_is_sanitized(): void
    {
        $prof = $this->prof();
        $art = TeachingArtifact::create(['teacher_id' => $prof->id, 'type' => 'summary', 'title' => 'X',
            'status' => 'ready',
            'content' => "<script>alert(1)</script>\n\n[clic](javascript:alert(2))\n\n[ok](https://e.it)"]);

        $html = $this->asProf($prof)->get(route('docente.artifacts.show', $art))->getContent();

        // Forme PERICOLOSE renderizzate: non devono esistere.
        // (Il contenuto grezzo può comparire — escapato — nella textarea di editing.)
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
        // Il link sicuro è renderizzato come href.
        $this->assertStringContainsString('href="https://e.it"', $html);
    }

    // ===== Rate limit: throttle sull'auto-generazione studente =====

    public function test_generation_endpoint_is_throttled_per_minute(): void
    {
        Bus::fake();
        atheneum_setting_put('schola.student_daily_generations', 100); // alza il tetto giornaliero per isolare il throttle/min
        $prof = $this->prof(); $class = $this->klass($prof);
        $art = TeachingArtifact::create(['teacher_id' => $prof->id, 'type' => 'summary', 'title' => 'A', 'content' => 'c', 'status' => 'ready']);
        $pub = ArtifactPublication::create(['teaching_artifact_id' => $art->id, 'school_class_id' => $class->id, 'students_can_generate' => true, 'published_at' => now()]);
        $s = $this->student(); ClassStudent::create(['school_class_id' => $class->id, 'student_id' => $s->id, 'status' => 'active']);

        for ($i = 0; $i < 10; $i++) {
            $this->asStudent($s)->post(route('student.classes.artifact.generate', [$class, $pub]), ['type' => 'mindmap']);
        }
        // throttle:schola-generate = 8/min → le richieste oltre l'8ª sono respinte
        // PRIMA del controller, quindi non creano righe: esattamente 8 generazioni.
        $this->assertSame(8, \App\Models\StudentGeneratedArtifact::where('student_id', $s->id)->count());
    }
}
