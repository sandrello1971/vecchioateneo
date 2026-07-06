<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\ClassConversation;
use App\Models\ClassMessage;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\Schola\ClassMessagingAccess;
use App\Services\Schola\TeacherClassAccess;
use Illuminate\Http\Request;

/**
 * "Messaggi" del docente: casella UNICA che raccoglie i thread di TUTTE le classi
 * che insegna, e compositore per un nuovo messaggio (a tutta la classe o a un
 * singolo studente). La lettura/risposta del singolo thread resta sulle rotte
 * per-classe esistenti (docente.classi.messaggi.*). Riuso di ClassMessagingAccess.
 */
class MessageInboxController extends Controller
{
    public function __construct(
        private ClassMessagingAccess $access,
        private TeacherClassAccess $classAccess,
    ) {}

    private function teacher(): Student
    {
        return Student::findOrFail(session('student_id'));
    }

    public function index()
    {
        $teacher = $this->teacher();

        $conversations = ClassConversation::query()
            ->where('teacher_id', $teacher->id)
            ->with(['student', 'schoolClass'])
            ->addSelect([
                'latest_body' => ClassMessage::select('body')
                    ->whereColumn('class_conversation_id', 'class_conversations.id')
                    ->orderByDesc('created_at')->limit(1),
            ])
            ->orderByDesc('last_message_at')
            ->get();

        $unread = $conversations->mapWithKeys(fn ($c) => [$c->id => $c->unreadCountFor($teacher)]);
        $classes = $this->classAccess->classesQuery($teacher->id)->orderBy('name')->get(['id', 'name']);

        return view('docente.messaggi-globali.index', compact('conversations', 'unread', 'classes'));
    }

    public function create()
    {
        $teacher = $this->teacher();
        $classes = $this->classAccess->classesQuery($teacher->id)->orderBy('name')->get();

        // Studenti attivi per classe → il select destinatario si aggiorna alla scelta classe.
        $studentsByClass = [];
        foreach ($classes as $c) {
            $studentsByClass[$c->id] = $this->access->activeStudentsOf($c)
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values();
        }

        return view('docente.messaggi-globali.create', compact('classes', 'studentsByClass'));
    }

    public function store(Request $request)
    {
        $teacher = $this->teacher();

        $data = $request->validate([
            'school_class_id' => 'required|uuid',
            'recipient' => 'required|string', // 'all' oppure id studente
            'subject' => 'required|string|min:3|max:200',
            'body' => 'required|string|min:1|max:5000',
        ]);

        $class = SchoolClass::findOrFail($data['school_class_id']);
        abort_unless($this->access->teacherTeaches($teacher->id, $class), 403, 'Non hai accesso a questa classe.');

        if ($data['recipient'] === 'all') {
            $count = $this->access->broadcastThread($class, $teacher, $data['subject'], $data['body']);
            abort_if($count === 0, 422, 'Nessuno studente attivo in questa classe.');

            return redirect()->route('docente.messages.index')
                ->with('success', "Messaggio inviato a {$count} studenti (ognuno in un thread privato).");
        }

        abort_unless($this->access->studentActive($class, $data['recipient']), 403,
            'Lo studente non è iscritto attivo in questa classe.');

        [$conversation, $message, $isNew] = $this->access->openThread(
            $class, $data['recipient'], $teacher->id, $teacher, $data['subject'], $data['body']
        );

        if ($isNew) {
            $this->access->notifyNewThread($conversation, $message, recipientIsTeacher: false);
        }

        return redirect()->route('docente.classi.messaggi.show', [$class, $conversation])
            ->with('success', 'Messaggio inviato.');
    }
}
