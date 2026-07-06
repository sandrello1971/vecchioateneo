<?php

namespace App\Services\Schola;

use App\Models\ClassConversation;
use App\Models\ClassMessage;
use App\Models\ClassStudent;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Notifications\ClassConversationCreatedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Regole di accesso della messaggistica didattica di classe (P22). Fonte unica
 * di "chi può messaggiare chi", costruita SOPRA TeacherClassAccess (cattedra/
 * proprietà) e l'iscrizione attiva. La segreteria (school_admin) non compare
 * mai: non insegna (niente cattedra) e non è iscritta come studente.
 */
class ClassMessagingAccess
{
    public function __construct(private TeacherClassAccess $access) {}

    /** Il docente ha accesso alla classe (cattedra o proprietà)? */
    public function teacherTeaches(string $teacherId, SchoolClass $class): bool
    {
        return $this->access->canTeach($teacherId, $class);
    }

    /** Lo studente ha iscrizione ATTIVA nella classe? */
    public function studentActive(SchoolClass $class, string $studentId): bool
    {
        return ClassStudent::where('school_class_id', $class->id)
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->exists();
    }

    /** Docenti messaggiabili da uno studente in questa classe. */
    public function teachersOf(SchoolClass $class): Collection
    {
        if ($class->school_id === null) {
            // Classe libera: il docente proprietario.
            return Student::where('id', $class->teacher_id)->get();
        }

        // Classe di scuola: i docenti con cattedra nella classe.
        $ids = TeachingAssignment::where('school_class_id', $class->id)
            ->pluck('teacher_id')->unique()->all();

        return Student::whereIn('id', $ids)->orderBy('name')->get();
    }

    /** Studenti attivi della classe (messaggiabili dal docente / destinatari annunci). */
    public function activeStudentsOf(SchoolClass $class): Collection
    {
        $ids = ClassStudent::where('school_class_id', $class->id)
            ->where('status', 'active')
            ->pluck('student_id')->all();

        return Student::whereIn('id', $ids)->orderBy('name')->get();
    }

    /**
     * Apre (o riusa) il thread unico per (classe, studente, docente) e accoda il
     * primo messaggio. Logica condivisa fra lato docente e lato studente.
     *
     * @return array{0: ClassConversation, 1: ClassMessage, 2: bool}  [thread, messaggio, isNew]
     */
    public function openThread(SchoolClass $class, string $studentId, string $teacherId, Student $sender, string $subject, string $body): array
    {
        $isNew = false;
        $conversation = null;
        $message = null;

        DB::transaction(function () use ($class, $studentId, $teacherId, $sender, $subject, $body, &$conversation, &$message, &$isNew) {
            $conversation = ClassConversation::where('school_class_id', $class->id)
                ->where('student_id', $studentId)
                ->where('teacher_id', $teacherId)
                ->first();

            if (!$conversation) {
                $isNew = true;
                $conversation = ClassConversation::create([
                    'school_class_id' => $class->id,
                    'student_id' => $studentId,
                    'teacher_id' => $teacherId,
                    'subject' => $subject,
                    'last_message_at' => now(),
                ]);
            }

            $message = ClassMessage::create([
                'class_conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'body' => $body,
            ]);
            $conversation->update(['last_message_at' => now()]);
        });

        return [$conversation, $message, $isNew];
    }

    /**
     * BROADCAST docente → tutti gli studenti attivi: apre/riusa il thread privato
     * 1:1 di ciascuno e vi accoda lo stesso messaggio. Ogni studente resta in un
     * thread separato (risponde SOLO al docente, mai ai compagni). Ritorna il numero
     * di studenti raggiunti.
     */
    public function broadcastThread(SchoolClass $class, Student $teacher, string $subject, string $body): int
    {
        $students = $this->activeStudentsOf($class);
        foreach ($students as $student) {
            [$conversation, $message, $isNew] = $this->openThread(
                $class, $student->id, $teacher->id, $teacher, $subject, $body
            );
            if ($isNew) {
                $this->notifyNewThread($conversation, $message, recipientIsTeacher: false);
            }
        }

        return $students->count();
    }

    /** Email all'altro partecipante alla creazione del thread (mirror corsi). */
    public function notifyNewThread(ClassConversation $conversation, ClassMessage $message, bool $recipientIsTeacher): void
    {
        $recipient = $conversation->otherParticipant($message->sender);
        if (!$recipient) {
            return;
        }
        try {
            $recipient->notify(new ClassConversationCreatedNotification($conversation, $message, $recipientIsTeacher));
        } catch (\Throwable $e) {
            Log::error('Notifica ClassConversationCreated fallita', [
                'conversation_id' => $conversation->id, 'error' => $e->getMessage(),
            ]);
        }
    }
}
