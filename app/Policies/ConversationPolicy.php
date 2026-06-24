<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class ConversationPolicy
{
    /**
     * Permette nuova conversation se nel CONTESTO DEL CORSO esiste la coppia
     * student↔instructor tra initiator e target, e accepts_dm=true.
     *
     * Discriminazione studente/formatore via DB (student_course / course_instructor),
     * NON via Student->role: in Officina un admin globale può essere formatore di un
     * corso senza role='instructor' (es. Stefano è role='admin' ma insegna RUMORE DI FONDO).
     */
    public function startConversationWith(Student $initiator, Student $target, Course $course): bool
    {
        $roles = $this->classifyForCourse($initiator, $target, $course);
        if (!$roles) {
            return false;
        }

        return DB::table('course_instructor')
            ->where('instructor_id', $roles['instructor_id'])
            ->where('course_id', $course->id)
            ->where('accepts_dm', true)
            ->exists();
    }

    /**
     * Risolve quale dei due (initiator/target) è studente e quale formatore nel
     * contesto del corso, basandosi sulle pivot table. Ritorna null se la coppia
     * non è valida (es. entrambi studenti, o nessuno dei due è iscritto/assegnato).
     */
    private function classifyForCourse(Student $initiator, Student $target, Course $course): ?array
    {
        $initiatorIsStudent = DB::table('student_course')
            ->where('student_id', $initiator->id)
            ->where('course_id', $course->id)
            ->where('is_active', true)
            ->exists();
        $initiatorIsInstructor = DB::table('course_instructor')
            ->where('instructor_id', $initiator->id)
            ->where('course_id', $course->id)
            ->exists();
        $targetIsStudent = DB::table('student_course')
            ->where('student_id', $target->id)
            ->where('course_id', $course->id)
            ->where('is_active', true)
            ->exists();
        $targetIsInstructor = DB::table('course_instructor')
            ->where('instructor_id', $target->id)
            ->where('course_id', $course->id)
            ->exists();

        if ($initiatorIsStudent && $targetIsInstructor) {
            return ['student_id' => $initiator->id, 'instructor_id' => $target->id];
        }
        if ($initiatorIsInstructor && $targetIsStudent) {
            return ['student_id' => $target->id, 'instructor_id' => $initiator->id];
        }
        return null;
    }

    /**
     * Esposto pubblicamente per il controller, che deve sapere quale dei 2 utenti
     * passare come student_id / instructor_id nella row Conversation.
     */
    public function classifyParticipants(Student $initiator, Student $target, Course $course): ?array
    {
        return $this->classifyForCourse($initiator, $target, $course);
    }

    public function view(Student $user, Conversation $conversation): bool
    {
        return $user->id === $conversation->student_id
            || $user->id === $conversation->instructor_id;
    }

    /**
     * Le conversation esistenti restano attive anche se accepts_dm passa a false:
     * il toggle blocca solo nuovi thread, non le repliche dentro thread aperti.
     */
    public function reply(Student $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
