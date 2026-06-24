<?php

namespace App\Http\Controllers\Student;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function store(StoreMessageRequest $request, Conversation $conversation)
    {
        $user = Student::findOrFail(session('student_id'));

        if (!$user->can('reply', $conversation)) {
            abort(403);
        }

        $data = $request->validated();

        $message = null;
        DB::transaction(function () use ($conversation, $user, $data, &$message) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $user->id,
                'body'            => $data['body'],
            ]);

            $conversation->update(['last_message_at' => now()]);
        });

        // Nessuna email su messaggi follow-up (decisione 7a).
        // Broadcast real-time via Reverb (Fase C): MessageSent va a
        // conversation.{id} (thread aperto live) + user.{recipient} (badge).
        $message->load('sender');
        broadcast(new MessageSent($conversation, $message))->toOthers();

        return redirect()->route('student.messages.show', $conversation);
    }
}
