<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Messaggi di un thread di classe. Rispecchia `messages` (read_at = ricevuta di
// lettura sul singolo messaggio).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_messages', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('class_conversation_id')->constrained('class_conversations')->cascadeOnDelete();
            $table->foreignUuid('sender_id')->constrained('students')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['class_conversation_id', 'created_at']);
            $table->index(['class_conversation_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_messages');
    }
};
