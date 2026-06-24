<?php

namespace App\Http\Controllers\Student;

use App\Events\ConversationCreated;
use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreConversationRequest;
use App\Models\Conversation;
use App\Models\Course;
use App\Models\Message;
use App\Models\Student;
use App\Notifications\ConversationCreatedNotification;
use App\Policies\ConversationPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConversationController extends Controller
{
    /**
     * Officina non usa il guard Laravel: l'utente loggato è recuperato dalla
     * session('student_id') (vedi student.auth middleware). Helper centralizzato.
     */
    private function currentUser(): Student
    {
        return Student::findOrFail(session('student_id'));
    }

    public function index()
    {
        $user = $this->currentUser();

        // Preview ultimo messaggio via addSelect subquery (vedi Conversation
        // model per il perché: PostgreSQL non ha MAX(uuid)).
        $conversations = Conversation::query()
            ->where(function ($q) use ($user) {
                $q->where('student_id', $user->id)
                  ->orWhere('instructor_id', $user->id);
            })
            ->with(['student', 'instructor', 'course'])
            ->addSelect([
                'latest_body' => Message::select('body')
                    ->whereColumn('conversation_id', 'conversations.id')
                    ->orderByDesc('created_at')
                    ->limit(1),
                'latest_sender_id' => Message::select('sender_id')
                    ->whereColumn('conversation_id', 'conversations.id')
                    ->orderByDesc('created_at')
                    ->limit(1),
            ])
            ->orderByDesc('last_message_at')
            ->paginate(20);

        return view('student.messages.index', compact('conversations'));
    }

    public function create(Request $request)
    {
        $user = $this->currentUser();

        $instructor = ($id = $request->query('instructor')) ? Student::find($id) : null;
        $course = ($id = $request->query('course')) ? Course::find($id) : null;

        if (!$instructor || !$course) {
            return redirect()->route('student.messages.index')
                ->with('error', 'Parametri mancanti per nuova conversazione.');
        }

        if (!$user->can('startConversationWith', [Conversation::class, $instructor, $course])) {
            return redirect()->route('student.messages.index')
                ->with('error', 'Non puoi iniziare una conversazione con questo formatore in questo corso.');
        }

        return view('student.messages.create', compact('instructor', 'course'));
    }

    public function store(StoreConversationRequest $request)
    {
        $user = $this->currentUser();
        $data = $request->validated();

        $instructor = Student::find($data['instructor_id']);
        $course = Course::find($data['course_id']);

        if (!$user->can('startConversationWith', [Conversation::class, $instructor, $course])) {
            return back()->with('error', 'Non puoi iniziare una conversazione con questo formatore in questo corso.')->withInput();
        }

        // Discriminazione student/instructor via DB pivot (non Student->role), perché
        // un admin può essere anche formatore di un corso. La policy l'ha già validato.
        $roles = (new ConversationPolicy())->classifyParticipants($user, $instructor, $course);
        if (!$roles) {
            return back()->with('error', 'Coppia non valida per questo corso.')->withInput();
        }

        $conversation = null;
        $message = null;

        DB::transaction(function () use ($data, $roles, $user, &$conversation, &$message) {
            $conversation = Conversation::create([
                'student_id'      => $roles['student_id'],
                'instructor_id'   => $roles['instructor_id'],
                'course_id'       => $data['course_id'],
                'subject'         => $data['subject'],
                'last_message_at' => now(),
            ]);

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $user->id,
                'body'            => $data['body'],
            ]);
        });

        // Notifica email all'altro partecipante (UNA SOLA volta, alla creazione)
        $recipient = $conversation->otherParticipant($user);
        if ($recipient) {
            try {
                $recipient->notify(new ConversationCreatedNotification($conversation, $message));
            } catch (\Throwable $e) {
                Log::error('Errore invio notifica ConversationCreated', [
                    'conversation_id' => $conversation->id,
                    'recipient_id'    => $recipient->id,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        // Broadcast real-time (Fase C):
        // - ConversationCreated → user.{recipient}: inbox + sidebar live
        // - MessageSent → conversation.{id} + user.{recipient}: thread + badge
        $message->load('sender');
        $conversation->load(['student', 'instructor', 'course']);
        broadcast(new ConversationCreated($conversation, $user->id))->toOthers();
        broadcast(new MessageSent($conversation, $message))->toOthers();

        Log::info('Nuova conversation creata', [
            'conversation_id' => $conversation->id,
            'initiator_id'    => $user->id,
            'recipient_id'    => $recipient?->id,
        ]);

        return redirect()->route('student.messages.show', $conversation)
            ->with('success', 'Conversazione avviata. Il formatore riceverà una notifica via email.');
    }

    public function show(Conversation $conversation)
    {
        $user = $this->currentUser();

        if (!$user->can('view', $conversation)) {
            abort(403);
        }

        $conversation->load(['messages.sender', 'student', 'instructor', 'course']);
        $conversation->markReadFor($user);

        return view('student.messages.show', compact('conversation'));
    }

    public function markRead(Conversation $conversation)
    {
        $user = $this->currentUser();

        if (!$user->can('view', $conversation)) {
            abort(403);
        }

        $count = $conversation->markReadFor($user);

        return response()->json(['marked_read' => $count]);
    }
}
