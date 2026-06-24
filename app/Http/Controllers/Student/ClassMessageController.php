<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\ResolvesScholaAccess;
use App\Models\ClassConversation;
use App\Models\ClassMessage;
use App\Models\SchoolClass;
use App\Services\Schola\ClassMessagingAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Messaggistica di classe lato STUDENTE (P22). Rispecchia ConversationController/
// MessageController del mondo corsi, scoping per iscrizione ATTIVA. Lo studente
// scrive ai DOCENTI della sua classe; non può messaggiare la segreteria.
class ClassMessageController extends Controller
{
    use ResolvesScholaAccess;

    public function __construct(private ClassMessagingAccess $access) {}

    private function authorizeClass(SchoolClass $class, string $studentId): void
    {
        $this->assertActiveEnrollment($class, $studentId);
    }

    private function authorizeConversation(ClassConversation $conversation, SchoolClass $class, string $studentId): void
    {
        abort_unless($conversation->school_class_id === $class->id
            && $conversation->student_id === $studentId, 403);
    }

    public function index(SchoolClass $class)
    {
        $student = $this->currentStudent();
        $this->authorizeClass($class, $student->id);

        $conversations = ClassConversation::query()
            ->where('school_class_id', $class->id)
            ->where('student_id', $student->id)
            ->with('teacher')
            ->addSelect([
                'latest_body' => ClassMessage::select('body')
                    ->whereColumn('class_conversation_id', 'class_conversations.id')
                    ->orderByDesc('created_at')->limit(1),
            ])
            ->orderByDesc('last_message_at')
            ->get();

        $unread = $conversations->mapWithKeys(fn ($c) => [$c->id => $c->unreadCountFor($student)]);
        $teachers = $this->access->teachersOf($class);

        return view('student.classi.messaggi.index', compact('class', 'conversations', 'unread', 'teachers'));
    }

    public function store(Request $request, SchoolClass $class)
    {
        $student = $this->currentStudent();
        $this->authorizeClass($class, $student->id);

        $data = $request->validate([
            'teacher_id' => 'required|uuid',
            'subject' => 'required|string|min:3|max:200',
            'body' => 'required|string|min:1|max:5000',
        ]);

        // Il destinatario deve essere un docente di QUESTA classe (cattedra/proprietà).
        abort_unless($this->access->teacherTeaches($data['teacher_id'], $class), 403,
            'Questo docente non insegna nella tua classe.');

        [$conversation, $message, $isNew] = $this->access->openThread(
            $class, $student->id, $data['teacher_id'], $student, $data['subject'], $data['body']
        );

        if ($isNew) {
            // Lo studente inizia → il destinatario è il docente.
            $this->access->notifyNewThread($conversation, $message, recipientIsTeacher: true);
        }

        return redirect()->route('student.classi.messaggi.show', [$class, $conversation])
            ->with('success', 'Messaggio inviato al docente.');
    }

    public function show(SchoolClass $class, ClassConversation $conversation)
    {
        $student = $this->currentStudent();
        $this->authorizeClass($class, $student->id);
        $this->authorizeConversation($conversation, $class, $student->id);

        $conversation->load(['messages.sender', 'teacher']);
        $conversation->markReadFor($student);

        return view('student.classi.messaggi.show', compact('class', 'conversation'));
    }

    public function reply(Request $request, SchoolClass $class, ClassConversation $conversation)
    {
        $student = $this->currentStudent();
        $this->authorizeClass($class, $student->id);
        $this->authorizeConversation($conversation, $class, $student->id);

        $data = $request->validate(['body' => 'required|string|min:1|max:5000']);

        DB::transaction(function () use ($conversation, $student, $data) {
            ClassMessage::create([
                'class_conversation_id' => $conversation->id,
                'sender_id' => $student->id,
                'body' => $data['body'],
            ]);
            $conversation->update(['last_message_at' => now()]);
        });

        return redirect()->route('student.classi.messaggi.show', [$class, $conversation]);
    }

    public function markRead(SchoolClass $class, ClassConversation $conversation)
    {
        $student = $this->currentStudent();
        $this->authorizeClass($class, $student->id);
        $this->authorizeConversation($conversation, $class, $student->id);

        return response()->json(['marked_read' => $conversation->markReadFor($student)]);
    }
}
