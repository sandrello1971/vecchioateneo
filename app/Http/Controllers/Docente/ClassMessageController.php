<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\ClassConversation;
use App\Models\ClassMessage;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Schola\ClassMessagingAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Messaggistica di classe lato DOCENTE (P22). Rispecchia ConversationController/
// MessageController del mondo corsi, scoping per cattedra (TeacherClassAccess).
class ClassMessageController extends Controller
{
    public function __construct(private ClassMessagingAccess $access) {}

    private function teacher(): Student
    {
        return Student::findOrFail(session('student_id'));
    }

    /** 403 se il docente non insegna nella classe. */
    private function authorizeClass(SchoolClass $class, Student $teacher): void
    {
        abort_unless($this->access->teacherTeaches($teacher->id, $class), 403,
            'Non hai accesso a questa classe.');
    }

    private function authorizeConversation(ClassConversation $conversation, SchoolClass $class, Student $teacher): void
    {
        abort_unless($conversation->school_class_id === $class->id
            && $conversation->teacher_id === $teacher->id, 403);
    }

    public function index(SchoolClass $class)
    {
        $teacher = $this->teacher();
        $this->authorizeClass($class, $teacher);

        $conversations = ClassConversation::query()
            ->where('school_class_id', $class->id)
            ->where('teacher_id', $teacher->id)
            ->with('student')
            ->addSelect([
                'latest_body' => ClassMessage::select('body')
                    ->whereColumn('class_conversation_id', 'class_conversations.id')
                    ->orderByDesc('created_at')->limit(1),
            ])
            ->orderByDesc('last_message_at')
            ->get();

        // Conteggi non letti per thread (niente N+1 grande: pochi thread per classe).
        $unread = $conversations->mapWithKeys(fn ($c) => [$c->id => $c->unreadCountFor($teacher)]);

        return view('docente.messaggi.index', compact('class', 'conversations', 'unread'));
    }

    public function create(SchoolClass $class)
    {
        $teacher = $this->teacher();
        $this->authorizeClass($class, $teacher);

        $students = $this->access->activeStudentsOf($class);

        return view('docente.messaggi.create', compact('class', 'students'));
    }

    public function store(Request $request, SchoolClass $class)
    {
        $teacher = $this->teacher();
        $this->authorizeClass($class, $teacher);

        $data = $request->validate([
            'student_id' => 'required|uuid',
            'subject' => 'required|string|min:3|max:200',
            'body' => 'required|string|min:1|max:5000',
        ]);

        abort_unless($this->access->studentActive($class, $data['student_id']), 403,
            'Lo studente non è iscritto attivo in questa classe.');

        [$conversation, $message, $isNew] = $this->access->openThread(
            $class, $data['student_id'], $teacher->id, $teacher, $data['subject'], $data['body']
        );

        if ($isNew) {
            // Il docente inizia → il destinatario è lo studente.
            $this->access->notifyNewThread($conversation, $message, recipientIsTeacher: false);
        }

        return redirect()->route('docente.classi.messaggi.show', [$class, $conversation])
            ->with('success', 'Messaggio inviato.');
    }

    public function show(SchoolClass $class, ClassConversation $conversation)
    {
        $teacher = $this->teacher();
        $this->authorizeClass($class, $teacher);
        $this->authorizeConversation($conversation, $class, $teacher);

        $conversation->load(['messages.sender', 'student']);
        $conversation->markReadFor($teacher);

        return view('docente.messaggi.show', compact('class', 'conversation'));
    }

    public function reply(Request $request, SchoolClass $class, ClassConversation $conversation)
    {
        $teacher = $this->teacher();
        $this->authorizeClass($class, $teacher);
        $this->authorizeConversation($conversation, $class, $teacher);

        $data = $request->validate(['body' => 'required|string|min:1|max:5000']);

        DB::transaction(function () use ($conversation, $teacher, $data) {
            ClassMessage::create([
                'class_conversation_id' => $conversation->id,
                'sender_id' => $teacher->id,
                'body' => $data['body'],
            ]);
            $conversation->update(['last_message_at' => now()]);
        });

        return redirect()->route('docente.classi.messaggi.show', [$class, $conversation]);
    }

    public function markRead(SchoolClass $class, ClassConversation $conversation)
    {
        $teacher = $this->teacher();
        $this->authorizeClass($class, $teacher);
        $this->authorizeConversation($conversation, $class, $teacher);

        return response()->json(['marked_read' => $conversation->markReadFor($teacher)]);
    }
}
