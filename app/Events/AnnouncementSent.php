<?php

namespace App\Events;

use App\Models\Announcement;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Emesso quando un formatore pubblica un annuncio. Broadcast a tutti gli
 * studenti iscritti attivi al corso (un PrivateChannel per ognuno).
 * Il frontend bumpa il badge "📢 Annunci" nella sidebar.
 */
class AnnouncementSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $announcementId;
    public string $subject;
    public string $instructorName;
    public string $courseName;
    /** @var string[] */
    public array $recipientIds;

    public function __construct(Announcement $announcement, array $recipientIds)
    {
        $this->announcementId = $announcement->id;
        $this->subject = $announcement->subject;
        $this->instructorName = $announcement->instructor->name;
        $this->courseName = $announcement->course->name;
        $this->recipientIds = $recipientIds;
    }

    public function broadcastOn(): array
    {
        return array_map(
            fn (string $id) => new PrivateChannel('user.' . $id),
            $this->recipientIds
        );
    }

    public function broadcastAs(): string
    {
        return 'AnnouncementSent';
    }

    public function broadcastWith(): array
    {
        return [
            'announcement_id'  => $this->announcementId,
            'subject'          => $this->subject,
            'instructor_name'  => $this->instructorName,
            'course_name'      => $this->courseName,
        ];
    }
}
