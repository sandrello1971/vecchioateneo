<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\EvaluatesExamState;
use App\Models\Course;
use App\Models\Module;
use App\Models\Student;
use App\Services\RagService;
use App\Services\VideoAIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class VideoController extends Controller
{
    use EvaluatesExamState;

    public function __construct(private VideoAIService $videoAI) {}

    private function checkVideoAccess(string $videoId): bool
    {
        $student = Student::findOrFail(session('student_id'));

        $module = Module::where('video_ai_id', $videoId)->first();
        $courseId = $module?->course_id;

        if (!$courseId) {
            $course = Course::where('video_ai_id', $videoId)->first();
            $courseId = $course?->id;
        }

        if (!$courseId) return false;

        if ($student->auto_enroll_all_courses) {
            return Course::where('id', $courseId)->where('is_active', true)->exists();
        }

        return $student->courses()
            ->wherePivot('is_active', true)
            ->where('courses.id', $courseId)
            ->exists();
    }

    private function ensureEnrolled(Course $course): Student
    {
        $student = Student::findOrFail(session('student_id'));

        if ($student->auto_enroll_all_courses && $course->is_active) {
            return $student;
        }

        $enrolled = $student->courses()
            ->where('courses.id', $course->id)
            ->wherePivot('is_active', true)
            ->exists();
        abort_unless($enrolled, 403, 'Non sei iscritto a questo corso.');
        return $student;
    }

    public function stream(Request $request, string $videoId)
    {
        if (!$this->checkVideoAccess($videoId)) abort(403);

        $url = config('services.videoai.url') . "/api/videos/{$videoId}/stream";
        // Proxy server-side: inoltra l'auth interna a videoai (header X-Internal-Token).
        $headers = ['X-Internal-Token' => (string) config('services.videoai.token')];
        if ($request->header('Range')) {
            $headers['Range'] = $request->header('Range');
        }

        $client = new \GuzzleHttp\Client();
        $upstream = $client->get($url, [
            'headers' => $headers,
            'stream' => true,
            'timeout' => 0,
            'http_errors' => false,
        ]);

        $body = $upstream->getBody();
        $status = $upstream->getStatusCode();

        $responseHeaders = array_filter([
            'Content-Type' => $upstream->getHeaderLine('Content-Type') ?: 'video/mp4',
            'Content-Length' => $upstream->getHeaderLine('Content-Length') ?: null,
            'Content-Range' => $upstream->getHeaderLine('Content-Range') ?: null,
            'Accept-Ranges' => 'bytes',
        ]);

        return response()->stream(function () use ($body) {
            while (!$body->eof()) {
                echo $body->read(8192);
                if (ob_get_level() > 0) ob_flush();
                flush();
            }
        }, $status, $responseHeaders);
    }

    public function thumbnail(string $videoId)
    {
        if (!$this->checkVideoAccess($videoId)) abort(403);

        $url = config('services.videoai.url') . "/api/videos/{$videoId}/thumbnail";
        // Proxy server-side: inoltra l'auth interna a videoai (header X-Internal-Token).
        $response = Http::timeout(10)
            ->withHeaders(['X-Internal-Token' => (string) config('services.videoai.token')])
            ->get($url);

        return response($response->body(), $response->status())
            ->header('Content-Type', 'image/jpeg');
    }

    public function chat(Request $request, string $videoId)
    {
        if (!$this->checkVideoAccess($videoId)) abort(403);

        $studentId = session('student_id');
        if ($studentId && $this->hasActiveExam($studentId)) {
            return response()->json([
                'error' => 'Minerva non è disponibile durante un esame in corso.',
                'exam_lock' => true,
            ], 423);
        }

        $request->validate([
            'question' => 'required|string|max:1000',
            'history' => 'nullable|array',
        ]);

        try {
            $result = $this->videoAI->chat(
                $videoId,
                $request->question,
                $request->history ?? []
            );
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function transcript(string $videoId)
    {
        if (!$this->checkVideoAccess($videoId)) abort(403);
        return response()->json($this->videoAI->getTranscript($videoId));
    }

    public function status(string $videoId)
    {
        if (!$this->checkVideoAccess($videoId)) abort(403);
        return response()->json($this->videoAI->getStatus($videoId));
    }

    public function searchInCourse(Request $request, Course $course, RagService $rag)
    {
        $this->ensureEnrolled($course);
        $data = $request->validate(['q' => 'required|string|max:500']);
        $results = $rag->searchVideos($data['q'], $course->id, null, 10);
        return response()->json(['results' => $results]);
    }

    public function searchInModule(Request $request, Course $course, Module $module, RagService $rag)
    {
        $this->ensureEnrolled($course);
        abort_unless($module->course_id === $course->id, 404);
        $data = $request->validate(['q' => 'required|string|max:500']);
        $results = $rag->searchVideos($data['q'], $course->id, $module->id, 10);
        return response()->json(['results' => $results]);
    }
}
