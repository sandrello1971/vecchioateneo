<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ParseCourseDocumentsJob;
use App\Models\Course;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Support\CourseIngestProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CourseIngestController extends Controller
{
    public function form()
    {
        return view('admin.courses.ingest.form');
    }

    public function parse(Request $request)
    {
        $request->validate([
            // .md è text/plain → la regola mimes non basta: si usa extensions (Laravel 13.5).
            'manual_file' => 'required|file|extensions:docx,doc,md,markdown|max:51200',
            'exam_file' => 'nullable|file|mimes:docx,doc|max:20480',
            'color' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:20',
            'certification_name' => 'nullable|string|max:255',
            'duration_hours' => 'nullable|integer',
        ]);

        $jobId = (string) Str::uuid();
        $stagingDir = "ingest-staging/{$jobId}";

        $manualExt = $request->file('manual_file')->getClientOriginalExtension();
        $manualPath = $request->file('manual_file')->storeAs($stagingDir, "manual.{$manualExt}", 'local');

        $examPath = null;
        if ($request->hasFile('exam_file')) {
            $examExt = $request->file('exam_file')->getClientOriginalExtension();
            $examPath = $request->file('exam_file')->storeAs($stagingDir, "exam.{$examExt}", 'local');
        }

        CourseIngestProgress::init($jobId);

        ParseCourseDocumentsJob::dispatch(
            $jobId,
            $manualPath,
            $examPath,
            [
                'color' => $request->input('color', '#55B1AE'),
                'icon' => $request->input('icon', '✦'),
                'certification_name' => $request->input('certification_name'),
                'duration_hours' => $request->input('duration_hours'),
            ]
        )->onQueue('ingest');

        session(['course_ingest_job_id' => $jobId]);

        return redirect()->route('admin.courses.ingest.processing', ['job' => $jobId]);
    }

    public function processing(Request $request)
    {
        $jobId = $request->query('job');
        abort_unless($jobId && CourseIngestProgress::get($jobId), 404);
        return view('admin.courses.ingest.processing', ['jobId' => $jobId]);
    }

    public function status(Request $request)
    {
        $jobId = $request->query('job');
        $data = $jobId ? CourseIngestProgress::get($jobId) : null;
        abort_unless($data, 404);
        return response()->json($data);
    }

    public function preview(Request $request)
    {
        $jobId = $request->query('job') ?? session('course_ingest_job_id');
        $data = $jobId ? CourseIngestProgress::get($jobId) : null;

        if (!$data || empty($data['done']) || !empty($data['error']) || !isset($data['result'])) {
            return redirect()->route('admin.courses.ingest.form')
                ->with('error', 'Risultato non disponibile o scaduto. Ricarica i documenti.');
        }

        return view('admin.courses.ingest.preview', [
            'data' => $data['result'],
            'jobId' => $jobId,
        ]);
    }

    public function confirm(Request $request)
    {
        $jobId = $request->input('job_id') ?? session('course_ingest_job_id');
        $cacheData = $jobId ? CourseIngestProgress::get($jobId) : null;

        if (!$cacheData || empty($cacheData['done']) || !isset($cacheData['result'])) {
            return redirect()->route('admin.courses.ingest.form')->with('error', 'Sessione scaduta. Ricarica.');
        }

        $data = $cacheData['result'];
        $settings = $data['settings'] ?? [];
        $examPrepHtml = $data['exam_prep_html'] ?? null;

        $input = $request->validate([
            'course_name' => 'required|string|max:255',
            'course_slug' => 'nullable|string|max:255',
            'course_description' => 'nullable|string',
            'course_short_description' => 'nullable|string|max:255',
            'modules' => 'required|array|min:1',
            'modules.*.title' => 'required|string|max:255',
            'modules.*.description' => 'nullable|string',
            'modules.*.content_html' => 'nullable|string',
            'modules.*.include' => 'nullable|string',
            'quiz_title' => 'nullable|string|max:255',
            'quiz_passing_score' => 'nullable|integer|min:0|max:100',
            'questions' => 'nullable|array',
            'questions.*.question' => 'nullable|string',
            'questions.*.options' => 'nullable|array',
            'questions.*.correct_answer' => 'nullable|string',
            'questions.*.explanation' => 'nullable|string',
            'questions.*.include' => 'nullable|string',
        ]);

        try {
            $courseId = DB::transaction(function () use ($input, $settings, $examPrepHtml) {
                $course = Course::create([
                    'name' => $input['course_name'],
                    'slug' => $input['course_slug'] ?: Str::slug($input['course_name']),
                    'description' => $input['course_description'] ?? null,
                    'short_description' => $input['course_short_description'] ?? null,
                    'exam_prep_html' => $examPrepHtml,
                    'color' => $settings['color'] ?? '#55B1AE',
                    'icon' => $settings['icon'] ?? '✦',
                    'certification_name' => $settings['certification_name'] ?? null,
                    'duration_hours' => $settings['duration_hours'] ?? null,
                    'is_active' => true,
                    'sort_order' => (Course::max('sort_order') ?? 0) + 1,
                ]);

                $sort = 0;
                foreach ($input['modules'] as $m) {
                    if (empty($m['include'])) continue;
                    Module::create([
                        'course_id' => $course->id,
                        'title' => $m['title'],
                        'description' => $m['description'] ?? null,
                        'content' => $m['content_html'] ?? null,
                        'is_active' => true,
                        'sort_order' => $sort++,
                    ]);
                }

                if (!empty($input['questions'])) {
                    $included = array_values(array_filter($input['questions'], fn($q) => !empty($q['include'])));
                    if (!empty($included)) {
                        $quiz = Quiz::create([
                            'course_id' => $course->id,
                            'module_id' => null,
                            'title' => $input['quiz_title'] ?? ('Esame finale — ' . $course->name),
                            'description' => 'Esame finale del corso',
                            'passing_score' => $input['quiz_passing_score'] ?? 70,
                            'time_limit_minutes' => null,
                            'max_attempts' => 3,
                            'randomize_questions' => true,
                            'show_results_immediately' => true,
                            'is_active' => true,
                        ]);

                        $qSort = 0;
                        foreach ($included as $q) {
                            $options = array_values(array_filter($q['options'] ?? [], fn($o) => is_string($o) && trim($o) !== ''));
                            if (count($options) !== 4) continue;
                            QuizQuestion::create([
                                'quiz_id' => $quiz->id,
                                'question' => $q['question'],
                                'type' => 'multiple_choice',
                                'options' => $options,
                                'correct_answer' => $q['correct_answer'] ?? $options[0],
                                'explanation' => $q['explanation'] ?? '',
                                'points' => 1,
                                'sort_order' => $qSort++,
                            ]);
                        }
                    }
                }

                return $course->id;
            });

            CourseIngestProgress::forget($jobId);
            session()->forget('course_ingest_job_id');
            Storage::disk('local')->deleteDirectory("ingest-staging/{$jobId}");

            return redirect("/admin/courses/{$courseId}/edit")->with('success', 'Corso creato dai documenti!');
        } catch (\Exception $e) {
            Log::error('Course ingest confirm failed: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Creazione fallita: ' . $e->getMessage());
        }
    }

    public function cancel(Request $request)
    {
        $jobId = $request->input('job_id') ?? session('course_ingest_job_id');
        if ($jobId) {
            CourseIngestProgress::forget($jobId);
            Storage::disk('local')->deleteDirectory("ingest-staging/{$jobId}");
        }
        session()->forget('course_ingest_job_id');
        session()->forget('course_ingest');
        return redirect()->route('admin.courses.index')->with('success', 'Ingestione annullata.');
    }
}
