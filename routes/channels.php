<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

/**
 * Channel privato per-conversation: ricevono MessageSent solo i 2 partecipanti.
 * Usato in show.blade.php per appendere il nuovo messaggio live.
 */
Broadcast::channel('conversation.{id}', function ($user, string $id) {
    $conv = Conversation::find($id);
    if (!$conv) {
        return false;
    }
    return $user->id === $conv->student_id || $user->id === $conv->instructor_id;
});

/**
 * Channel privato per-utente: usato per badge unread live nella sidebar e per
 * notifiche di "nuovo thread aperto con te" quando l'utente non e' sulla pagina
 * del thread. Ognuno puo' subscribere solo al proprio user channel.
 */
Broadcast::channel('user.{id}', function ($user, string $id) {
    return $user->id === $id;
});

/**
 * Presence channel per-conversation: typing indicator. I 2 partecipanti
 * vedono chi e' "presente" (sta guardando il thread) e ricevono i whisper
 * events "typing" via .listenForWhisper('typing', ...).
 * Ritorna le info utente che vengono distribuite ai presenti.
 */
Broadcast::channel('presence-conversation.{id}', function ($user, string $id) {
    $conv = Conversation::find($id);
    if (!$conv) {
        return false;
    }
    if ($user->id !== $conv->student_id && $user->id !== $conv->instructor_id) {
        return false;
    }
    return ['id' => $user->id, 'name' => $user->name];
});
