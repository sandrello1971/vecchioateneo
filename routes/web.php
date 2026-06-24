<?php

use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'index'])->name('home');
Route::get('/primus', [PageController::class, 'primus'])->name('primus');
Route::get('/consilium', [PageController::class, 'consilium'])->name('consilium');
Route::get('/initium', [PageController::class, 'initium'])->name('initium');
Route::get('/structura', [PageController::class, 'structura'])->name('structura');
Route::get('/ai-agents-mcp', [PageController::class, 'aiAgentsMcp'])->name('ai-agents-mcp');
Route::get('/conformita-ai-act', [PageController::class, 'conformitaAiAct'])->name('conformita-ai-act');
Route::get('/risorse', [PageController::class, 'risorse'])->name('risorse');
Route::get('/contatti', [PageController::class, 'contatti'])->name('contatti');
Route::post('/contatti', [PageController::class, 'contatti'])->name('contatti.post');
Route::get('/privacy-policy', [PageController::class, 'privacyPolicy'])->name('privacy');
Route::get('/cookie-policy', [PageController::class, 'cookiePolicy'])->name('cookies');

Route::get('/mappa-percorso', [PageController::class, 'mappaPercorso'])->name('lead-magnet.show');
Route::get('/mappa-percorso/grazie', [PageController::class, 'mappaPercorsoGrazie'])->name('lead-magnet.thank-you');

Route::get('/sitemap.xml', function () {
    return response()->file(public_path('sitemap.xml'), ['Content-Type' => 'application/xml']);
});

// Verifica pubblica del certificato — fuori da student.auth, accessibile a chiunque
// abbia il codice. Rate-limit per-IP definito in AppServiceProvider.
Route::get('/certificato/verifica/{code}', [App\Http\Controllers\CertificateVerifyController::class, 'show'])
    ->middleware('throttle:certificate-verify')
    ->name('certificate.verify');

Route::get('/certificato/verifica/{code}/pdf', [App\Http\Controllers\CertificateVerifyController::class, 'downloadSigned'])
    ->middleware('throttle:certificate-verify')
    ->name('certificate.verify.pdf');

// ===== AREA STUDENTI =====
Route::prefix('learn')->name('student.')->group(function () {
    Route::get('/demo', [App\Http\Controllers\Student\DemoController::class, 'start'])->name('demo.start');
    Route::get('/login', [App\Http\Controllers\Student\AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [App\Http\Controllers\Student\AuthController::class, 'login'])->middleware('throttle:login')->name('login.post');
    Route::post('/logout', [App\Http\Controllers\Student\AuthController::class, 'logout'])->name('logout');
    Route::get('/change-password', [App\Http\Controllers\Student\AuthController::class, 'showChangePassword'])->name('change-password');
    Route::post('/change-password', [App\Http\Controllers\Student\AuthController::class, 'changePassword'])->name('change-password.post');

    // Iscrizione a una classe con codice (pacchetto 3). Pubblico: gestisce sia lo
    // studente loggato sia la registrazione di un nuovo studente via codice.
    Route::get('/classi/unisciti', [App\Http\Controllers\Student\ClassJoinController::class, 'create'])->name('classes.join.create');
    Route::post('/classi/unisciti', [App\Http\Controllers\Student\ClassJoinController::class, 'store'])
        ->middleware('throttle:class-join')->name('classes.join.store');

    Route::middleware(['student.auth', 'student.password', 'demo.restrictions'])->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\Student\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/classi', [App\Http\Controllers\Student\StudentClassController::class, 'index'])->name('classes.index');
        // Minerva di classe (pacchetto 6b): la chat usa /minerva/ask con school_class_id.
        Route::get('/classi/{class}/minerva', [App\Http\Controllers\Student\ChatController::class, 'showClass'])->name('classes.minerva');
        // Fruizione di classe (pacchetto 7)
        Route::get('/classi/{class}', [App\Http\Controllers\Student\StudentClassController::class, 'show'])->name('classes.show');

        // Fruizione lezioni (P20b): corpo + appunti per paragrafo + media + Minerva di lezione
        Route::get('/classi/{class}/lezioni/{lesson}', [App\Http\Controllers\Student\StudentLessonController::class, 'show'])->name('classes.lesson.show');
        Route::get('/classi/{class}/lezioni/{lesson}/materiali/{document}/sorgente', [App\Http\Controllers\Student\StudentLessonController::class, 'materialSource'])->name('classes.lesson.material.source');
        Route::get('/classi/{class}/lezioni/{lesson}/presentazione', [App\Http\Controllers\Student\StudentLessonController::class, 'presentation'])->name('classes.lesson.presentation');

        // Messaggistica di classe (P22) — thread col docente + annunci (sola lettura)
        Route::get('/classi/{class}/messaggi', [App\Http\Controllers\Student\ClassMessageController::class, 'index'])->name('classi.messaggi.index');
        Route::post('/classi/{class}/messaggi', [App\Http\Controllers\Student\ClassMessageController::class, 'store'])->name('classi.messaggi.store');
        Route::get('/classi/{class}/messaggi/{conversation}', [App\Http\Controllers\Student\ClassMessageController::class, 'show'])->name('classi.messaggi.show');
        Route::post('/classi/{class}/messaggi/{conversation}/messaggi', [App\Http\Controllers\Student\ClassMessageController::class, 'reply'])->name('classi.messaggi.reply');
        Route::patch('/classi/{class}/messaggi/{conversation}/letto', [App\Http\Controllers\Student\ClassMessageController::class, 'markRead'])->name('classi.messaggi.markRead');

        Route::get('/classi/{class}/annunci', [App\Http\Controllers\Student\ClassAnnouncementController::class, 'index'])->name('classi.annunci.index');
        Route::get('/classi/{class}/annunci/{announcement}', [App\Http\Controllers\Student\ClassAnnouncementController::class, 'show'])->name('classi.annunci.show');
        // Auto-generazione studente dalla lezione (P20c): quiz/autoverifica privato
        Route::post('/classi/{class}/lezioni/{lesson}/genera', [App\Http\Controllers\Student\StudentGenerationController::class, 'storeFromLesson'])->name('classes.lesson.generate')->middleware('throttle:schola-generate');
        Route::get('/classi/{class}/lezioni/{lesson}/generati/{generated}/stato', [App\Http\Controllers\Student\StudentGenerationController::class, 'lessonStatus'])->name('classes.lesson.generated.status');
        Route::post('/classi/{class}/lezioni/{lesson}/appunti', [App\Http\Controllers\Student\LessonNoteController::class, 'save'])->name('classes.lesson.notes.save');
        Route::get('/classi/{class}/lezioni/{lesson}/appunti', [App\Http\Controllers\Student\LessonNoteController::class, 'list'])->name('classes.lesson.notes.list');
        Route::delete('/lezioni-appunti/{note}', [App\Http\Controllers\Student\LessonNoteController::class, 'delete'])->name('classes.lesson.notes.delete');
        Route::get('/classi/{class}/artefatti/{publication}', [App\Http\Controllers\Student\StudentArtifactController::class, 'show'])->name('classes.artifact.show');
        Route::get('/classi/{class}/artefatti/{publication}/sorgente', [App\Http\Controllers\Student\StudentArtifactController::class, 'source'])->name('classes.artifact.source');
        Route::post('/classi/{class}/artefatti/{publication}/genera', [App\Http\Controllers\Student\StudentGenerationController::class, 'store'])->name('classes.artifact.generate')->middleware('throttle:schola-generate');
        Route::get('/classi/{class}/artefatti/{publication}/generati/{generated}/stato', [App\Http\Controllers\Student\StudentGenerationController::class, 'status'])->name('classes.artifact.generated.status');
        // Trasparenza (§8.1): informativa "l'attività di studio è visibile al docente"
        Route::view('/info/studio-condiviso', 'student.classi.trasparenza')->name('schola.transparency');
        Route::get('/course/{course:slug}', [App\Http\Controllers\Student\CourseController::class, 'show'])->name('course.show');
        Route::get('/course/{course:slug}/module/{module}', [App\Http\Controllers\Student\CourseController::class, 'module'])->name('module.show');
        Route::post('/course/{course:slug}/module/{module}/complete', [App\Http\Controllers\Student\CourseController::class, 'completeModule'])->name('module.complete');
        Route::get('/course/{course:slug}/module/{module}/canvas/{canvas}', [App\Http\Controllers\Student\CourseController::class, 'canvas'])->name('module.canvas');

        // P29 Fase 3 — PDF generato (modulo + dispensa corso), generazione on-access
        Route::get('/course/{course:slug}/module/{module}/documento', [App\Http\Controllers\Student\GeneratedDocumentController::class, 'module'])->name('module.document.download');
        Route::get('/course/{course:slug}/documento', [App\Http\Controllers\Student\GeneratedDocumentController::class, 'course'])->name('course.document.download');

        // Mappe concettuali a livello corso (lato studente)
        Route::get('/course/{course:slug}/concept-maps', [App\Http\Controllers\Student\ConceptMapController::class, 'index'])->name('course.concept-maps.index');
        Route::get('/course/{course:slug}/concept-map/{concept_map}', [App\Http\Controllers\Student\ConceptMapController::class, 'show'])->name('course.concept-map.show');
        Route::post('/course/{course:slug}/concept-map/{concept_map}/fork', [App\Http\Controllers\Student\ConceptMapController::class, 'fork'])->name('course.concept-map.fork');
        Route::get('/course/{course:slug}/concept-map/{concept_map}/my', [App\Http\Controllers\Student\ConceptMapController::class, 'editFork'])->name('course.concept-map.my');
        Route::patch('/course/{course:slug}/concept-map/{concept_map}/my', [App\Http\Controllers\Student\ConceptMapController::class, 'saveFork'])->name('course.concept-map.my.save');

        Route::get('/quiz/{quiz}', [App\Http\Controllers\Student\QuizController::class, 'show'])->name('quiz.show');
        Route::post('/quiz/{quiz}/start', [App\Http\Controllers\Student\QuizController::class, 'start'])->name('quiz.start');
        Route::post('/quiz/{quiz}/submit', [App\Http\Controllers\Student\QuizController::class, 'submit'])
            ->middleware('throttle:5,1')
            ->name('quiz.submit');
        Route::post('/quiz/{quiz}/abandon', [App\Http\Controllers\Student\QuizController::class, 'abandon'])->name('quiz.abandon');
        Route::get('/quiz/{quiz}/result/{attempt}', [App\Http\Controllers\Student\QuizController::class, 'result'])->name('quiz.result');

        Route::get('/chat/{course:slug}', [App\Http\Controllers\Student\ChatController::class, 'show'])->name('chat.show');
        Route::post('/chat/message', [App\Http\Controllers\Student\ChatController::class, 'sendMessage'])->middleware('throttle:minerva-chat')->name('chat.message');
        Route::post('/minerva/ask', [App\Http\Controllers\Student\ChatController::class, 'minervaAsk'])->middleware('throttle:minerva-chat')->name('minerva.ask');

        Route::get('/certificate/{course:slug}', [App\Http\Controllers\Student\CertificateController::class, 'download'])->name('certificate.download');
        Route::get('/certificate/{course:slug}/view', [App\Http\Controllers\Student\CertificateController::class, 'show'])->name('certificate.show');

        Route::post('/notes/{module}', [App\Http\Controllers\Student\NoteController::class, 'save'])->name('notes.save');
        Route::get('/notes/{module}', [App\Http\Controllers\Student\NoteController::class, 'list'])->name('notes.list');
        Route::delete('/notes/{note}', [App\Http\Controllers\Student\NoteController::class, 'delete'])->name('notes.delete');

        Route::get('/canvas/{material}/data', [App\Http\Controllers\Student\CanvasController::class, 'getData'])->name('canvas.get');
        Route::patch('/canvas/{material}/data', [App\Http\Controllers\Student\CanvasController::class, 'saveData'])->name('canvas.save');

        Route::get('/material/{material}/download', [App\Http\Controllers\Student\MaterialController::class, 'download'])->name('material.download');
        Route::get('/material/{material}/canvas', [App\Http\Controllers\Student\MaterialController::class, 'canvas'])->name('material.canvas');

        Route::get('/course/{course:slug}/instructor/{material}', [App\Http\Controllers\Student\InstructorMaterialController::class, 'show'])->name('instructor.material.show');
        Route::get('/course/{course:slug}/instructor/{material}/download', [App\Http\Controllers\Student\InstructorMaterialController::class, 'download'])->name('instructor.material.download');

        Route::prefix('documents')->name('documents.')->group(function () {
            Route::get('/',                    [App\Http\Controllers\Student\DocumentController::class, 'index'])->name('index');
            Route::post('/',                   [App\Http\Controllers\Student\DocumentController::class, 'store'])->name('store');
            Route::get('/{document}/download', [App\Http\Controllers\Student\DocumentController::class, 'download'])->name('download');
            Route::put('/{document}',          [App\Http\Controllers\Student\DocumentController::class, 'update'])->name('update');
            Route::delete('/{document}',       [App\Http\Controllers\Student\DocumentController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('docenti/documenti')->name('instructor_documents.')->group(function () {
            Route::get('/',                    [App\Http\Controllers\Student\InstructorSharedDocumentController::class, 'index'])->name('index');
            Route::get('/{document}/download', [App\Http\Controllers\Student\InstructorSharedDocumentController::class, 'download'])->name('download');
        });

        Route::prefix('knowledge-base')->name('knowledge_base.')->group(function () {
            Route::get('/', [App\Http\Controllers\Student\InstructorNoteController::class, 'index'])->name('index');
            Route::get('/create', [App\Http\Controllers\Student\InstructorNoteController::class, 'create'])->name('create');
            Route::post('/', [App\Http\Controllers\Student\InstructorNoteController::class, 'store'])->name('store');

            Route::post('/upload-image', [App\Http\Controllers\Student\InstructorNoteController::class, 'uploadImage'])->name('upload-image');
            Route::get('/tag-suggest', [App\Http\Controllers\Student\InstructorNoteController::class, 'tagSuggest'])->name('tag-suggest');
            Route::get('/modules/{courseId}', [App\Http\Controllers\Student\InstructorNoteController::class, 'modulesByCourse'])->name('modules');
            Route::get('/sections/{courseId}', [App\Http\Controllers\Student\InstructorNoteController::class, 'sectionsByCourse'])->name('sections');

            Route::get('/{note}/edit', [App\Http\Controllers\Student\InstructorNoteController::class, 'edit'])->name('edit');
            Route::put('/{note}', [App\Http\Controllers\Student\InstructorNoteController::class, 'update'])->name('update');
            Route::delete('/{note}', [App\Http\Controllers\Student\InstructorNoteController::class, 'destroy'])->name('destroy');
            Route::post('/{note}/restore', [App\Http\Controllers\Student\InstructorNoteController::class, 'restore'])->name('restore');
        });

        Route::get('/video/{videoId}/stream', [App\Http\Controllers\Student\VideoController::class, 'stream'])->name('video.stream');
        Route::get('/video/{videoId}/thumbnail', [App\Http\Controllers\Student\VideoController::class, 'thumbnail'])->name('video.thumbnail');
        Route::post('/video/{videoId}/chat', [App\Http\Controllers\Student\VideoController::class, 'chat'])->name('video.chat');
        Route::get('/video/{videoId}/transcript', [App\Http\Controllers\Student\VideoController::class, 'transcript'])->name('video.transcript');
        Route::get('/video/{videoId}/status', [App\Http\Controllers\Student\VideoController::class, 'status'])->name('video.status');
        Route::get('/course/{course:slug}/video-search', [App\Http\Controllers\Student\VideoController::class, 'searchInCourse'])->name('video.search.course');
        Route::get('/course/{course:slug}/module/{module}/video-search', [App\Http\Controllers\Student\VideoController::class, 'searchInModule'])->name('video.search.module');

        // Impostazioni formatore (Fase D) — toggle accepts_dm per corso
        Route::get('/formatore/impostazioni', [App\Http\Controllers\Student\InstructorSettingsController::class, 'index'])->name('instructor_settings.index');
        Route::patch('/formatore/impostazioni/dm', [App\Http\Controllers\Student\InstructorSettingsController::class, 'updateDm'])->name('instructor_settings.updateDm');

        // Annunci (broadcast 1-to-many formatore → studenti corso)
        Route::get('/annunci',              [App\Http\Controllers\Student\AnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('/annunci/nuovo',        [App\Http\Controllers\Student\AnnouncementController::class, 'create'])->name('announcements.create');
        Route::post('/annunci',             [App\Http\Controllers\Student\AnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('/annunci/{announcement}', [App\Http\Controllers\Student\AnnouncementController::class, 'show'])->name('announcements.show');

        // Messaggi (DM) — backend Fase A (UI Fase B, Reverb Fase C)
        Route::prefix('messaggi')->name('messages.')->group(function () {
            Route::get('/',                          [App\Http\Controllers\Student\ConversationController::class, 'index'])->name('index');
            Route::get('/nuovo',                     [App\Http\Controllers\Student\ConversationController::class, 'create'])->name('create');
            Route::post('/',                         [App\Http\Controllers\Student\ConversationController::class, 'store'])->name('store');
            Route::get('/{conversation}',            [App\Http\Controllers\Student\ConversationController::class, 'show'])->name('show');
            Route::patch('/{conversation}/letto',    [App\Http\Controllers\Student\ConversationController::class, 'markRead'])->name('markRead');
            Route::post('/{conversation}/messaggi',  [App\Http\Controllers\Student\MessageController::class, 'store'])->name('messages.store');
        });
    });
});

// ===== AREA DOCENTE SCHOLA =====
// Auth via sessione studente + gate professor. NON eredita gli accessi instructor.
Route::prefix('docente')->name('docente.')->middleware(['student.auth', 'professor'])->group(function () {
    Route::get('/', [App\Http\Controllers\Docente\DashboardController::class, 'index'])->name('dashboard');

    // Classi (pacchetto 3)
    Route::get('/classi', [App\Http\Controllers\Docente\ClassController::class, 'index'])->name('classes.index');
    Route::post('/classi', [App\Http\Controllers\Docente\ClassController::class, 'store'])->name('classes.store');
    Route::get('/classi/{class}', [App\Http\Controllers\Docente\ClassController::class, 'show'])->name('classes.show');
    Route::patch('/classi/{class}', [App\Http\Controllers\Docente\ClassController::class, 'update'])->name('classes.update');

    // Messaggistica di classe (P22) — thread studente↔docente + annunci (cattedra)
    Route::get('/classi/{class}/messaggi', [App\Http\Controllers\Docente\ClassMessageController::class, 'index'])->name('classi.messaggi.index');
    Route::get('/classi/{class}/messaggi/nuovo', [App\Http\Controllers\Docente\ClassMessageController::class, 'create'])->name('classi.messaggi.create');
    Route::post('/classi/{class}/messaggi', [App\Http\Controllers\Docente\ClassMessageController::class, 'store'])->name('classi.messaggi.store');
    Route::get('/classi/{class}/messaggi/{conversation}', [App\Http\Controllers\Docente\ClassMessageController::class, 'show'])->name('classi.messaggi.show');
    Route::post('/classi/{class}/messaggi/{conversation}/messaggi', [App\Http\Controllers\Docente\ClassMessageController::class, 'reply'])->name('classi.messaggi.reply');
    Route::patch('/classi/{class}/messaggi/{conversation}/letto', [App\Http\Controllers\Docente\ClassMessageController::class, 'markRead'])->name('classi.messaggi.markRead');

    Route::get('/classi/{class}/annunci', [App\Http\Controllers\Docente\ClassAnnouncementController::class, 'index'])->name('classi.annunci.index');
    Route::get('/classi/{class}/annunci/nuovo', [App\Http\Controllers\Docente\ClassAnnouncementController::class, 'create'])->name('classi.annunci.create');
    Route::post('/classi/{class}/annunci', [App\Http\Controllers\Docente\ClassAnnouncementController::class, 'store'])->name('classi.annunci.store');
    Route::get('/classi/{class}/annunci/{announcement}', [App\Http\Controllers\Docente\ClassAnnouncementController::class, 'show'])->name('classi.annunci.show');
    Route::post('/classi/{class}/rigenera-codice', [App\Http\Controllers\Docente\ClassController::class, 'regenerateCode'])->name('classes.regenerate-code');
    Route::patch('/classi/{class}/studenti/{enrollment}', [App\Http\Controllers\Docente\ClassRosterController::class, 'update'])->name('classes.roster.update');
    // Minerva di classe lato docente (scope teacher_private + class). Stessa view, POST su /minerva/ask.
    Route::get('/classi/{class}/minerva', [App\Http\Controllers\Student\ChatController::class, 'showClass'])->name('classes.minerva');

    // Cruscotto (pacchetto 8)
    Route::get('/classi/{class}/attivita', [App\Http\Controllers\Docente\ClassActivityController::class, 'index'])->name('classes.activity');
    Route::get('/classi/{class}/domande-scoperte', [App\Http\Controllers\Docente\UnansweredQuestionsController::class, 'index'])->name('classes.questions');
    Route::post('/classi/{class}/domande-scoperte/azione', [App\Http\Controllers\Docente\UnansweredQuestionsController::class, 'updateCluster'])->name('classes.questions.bulk');
    Route::patch('/domande-scoperte/{question}', [App\Http\Controllers\Docente\UnansweredQuestionsController::class, 'update'])->name('questions.update');

    // Materiali grezzi (pacchetto 4a)
    Route::get('/materiali', [App\Http\Controllers\Docente\TeachingDocumentController::class, 'index'])->name('materials.index');
    Route::get('/materiali/crea', [App\Http\Controllers\Docente\TeachingDocumentController::class, 'create'])->name('materials.create');
    Route::post('/materiali', [App\Http\Controllers\Docente\TeachingDocumentController::class, 'store'])->name('materials.store')->middleware('throttle:schola-generate');
    Route::get('/materiali/{document}', [App\Http\Controllers\Docente\TeachingDocumentController::class, 'show'])->name('materials.show');
    Route::patch('/materiali/{document}', [App\Http\Controllers\Docente\TeachingDocumentController::class, 'update'])->name('materials.update');
    Route::delete('/materiali/{document}', [App\Http\Controllers\Docente\TeachingDocumentController::class, 'destroy'])->name('materials.destroy');
    Route::get('/materiali/{document}/file/{index}', [App\Http\Controllers\Docente\TeachingDocumentController::class, 'downloadSource'])->name('materials.download');
    Route::get('/materiali/{document}/stato', [App\Http\Controllers\Docente\TeachingDocumentController::class, 'status'])->name('materials.status');
    Route::post('/materiali/{document}/retry', [App\Http\Controllers\Docente\TeachingDocumentController::class, 'retry'])->name('materials.retry')->middleware('throttle:schola-generate');

    // Argomenti e Lezioni (fase 3, P18) — solo organizzazione, niente generazione
    Route::get('/argomenti', [App\Http\Controllers\Docente\TopicController::class, 'index'])->name('topics.index');
    Route::post('/argomenti', [App\Http\Controllers\Docente\TopicController::class, 'store'])->name('topics.store');
    Route::post('/argomenti/riordina', [App\Http\Controllers\Docente\TopicController::class, 'reorder'])->name('topics.reorder');
    Route::get('/argomenti/{topic}', [App\Http\Controllers\Docente\TopicController::class, 'show'])->name('topics.show');
    Route::patch('/argomenti/{topic}', [App\Http\Controllers\Docente\TopicController::class, 'update'])->name('topics.update');
    Route::delete('/argomenti/{topic}', [App\Http\Controllers\Docente\TopicController::class, 'destroy'])->name('topics.destroy');

    Route::post('/argomenti/{topic}/lezioni', [App\Http\Controllers\Docente\LessonController::class, 'store'])->name('lessons.store');
    Route::post('/argomenti/{topic}/lezioni/riordina', [App\Http\Controllers\Docente\LessonController::class, 'reorder'])->name('lessons.reorder');
    Route::get('/lezioni/{lesson}', [App\Http\Controllers\Docente\LessonController::class, 'show'])->name('lessons.show');
    Route::patch('/lezioni/{lesson}', [App\Http\Controllers\Docente\LessonController::class, 'update'])->name('lessons.update');
    Route::patch('/lezioni/{lesson}/contenuto', [App\Http\Controllers\Docente\LessonController::class, 'updateContent'])->name('lessons.content');
    Route::delete('/lezioni/{lesson}', [App\Http\Controllers\Docente\LessonController::class, 'destroy'])->name('lessons.destroy');
    Route::post('/lezioni/{lesson}/materiali', [App\Http\Controllers\Docente\LessonController::class, 'assignMaterial'])->name('lessons.materials.assign');
    Route::delete('/lezioni/{lesson}/materiali/{document}', [App\Http\Controllers\Docente\LessonController::class, 'unassignMaterial'])->name('lessons.materials.unassign');

    // Composizione corpo lezione (P19) — generazione, rigenerazione, polling
    Route::post('/lezioni/{lesson}/componi', [App\Http\Controllers\Docente\LessonGenerationController::class, 'generate'])->name('lessons.generate')->middleware('throttle:schola-generate');
    Route::post('/lezioni/{lesson}/ricomponi', [App\Http\Controllers\Docente\LessonGenerationController::class, 'regenerate'])->name('lessons.regenerate')->middleware('throttle:schola-generate');
    Route::get('/lezioni/{lesson}/stato', [App\Http\Controllers\Docente\LessonGenerationController::class, 'status'])->name('lessons.status');
    // Artefatti a livello di lezione (riuso GenerateArtifactJob via lesson_id)
    Route::post('/lezioni/{lesson}/artefatti', [App\Http\Controllers\Docente\LessonArtifactController::class, 'store'])->name('lessons.artifacts.generate')->middleware('throttle:schola-generate');

    // Note del docente per paragrafo (P20b) — didattiche, visibili agli studenti
    Route::post('/lezioni/{lesson}/note-docente', [App\Http\Controllers\Docente\LessonTeacherNoteController::class, 'save'])->name('lessons.teacher-notes.save');
    Route::get('/lezioni/{lesson}/note-docente', [App\Http\Controllers\Docente\LessonTeacherNoteController::class, 'list'])->name('lessons.teacher-notes.list');

    // Presentazione .pptx della lezione (P21) — generazione/rigenerazione/stato/download (owner)
    Route::post('/lezioni/{lesson}/presentazione', [App\Http\Controllers\Docente\LessonPresentationController::class, 'generate'])->name('lessons.presentation.generate')->middleware('throttle:schola-generate');
    Route::post('/lezioni/{lesson}/presentazione/rigenera', [App\Http\Controllers\Docente\LessonPresentationController::class, 'regenerate'])->name('lessons.presentation.regenerate')->middleware('throttle:schola-generate');
    Route::post('/lezioni/{lesson}/presentazione/correggi', [App\Http\Controllers\Docente\LessonPresentationController::class, 'edit'])->name('lessons.presentation.edit')->middleware('throttle:schola-generate');
    Route::get('/lezioni/{lesson}/presentazione/stato', [App\Http\Controllers\Docente\LessonPresentationController::class, 'status'])->name('lessons.presentation.status');
    Route::get('/lezioni/{lesson}/presentazione/download', [App\Http\Controllers\Docente\LessonPresentationController::class, 'download'])->name('lessons.presentation.download');
    Route::get('/lezioni/{lesson}/presentazione/slide/{n}', [App\Http\Controllers\Docente\LessonPresentationController::class, 'previewImage'])->whereNumber('n')->name('lessons.presentation.preview');

    // Pubblicazione lezione su classe (P20a) — cattedra/proprietà + ingestion RAG asincrona
    Route::post('/lezioni/{lesson}/pubblica', [App\Http\Controllers\Docente\LessonPublicationController::class, 'store'])->name('lessons.publish');
    Route::get('/lezioni/{lesson}/pubblicazioni/stato', [App\Http\Controllers\Docente\LessonPublicationController::class, 'status'])->name('lessons.publications.status');
    Route::delete('/pubblicazioni-lezione/{publication}', [App\Http\Controllers\Docente\LessonPublicationController::class, 'destroy'])->name('lesson-publications.destroy');

    // Generazione e gestione artefatti (pacchetto 5)
    Route::post('/materiali/{document}/genera', [App\Http\Controllers\Docente\ArtifactGenerationController::class, 'store'])->name('artifacts.generate')->middleware('throttle:schola-generate');
    Route::get('/artefatti/{artifact}', [App\Http\Controllers\Docente\ArtifactController::class, 'show'])->name('artifacts.show');
    Route::patch('/artefatti/{artifact}', [App\Http\Controllers\Docente\ArtifactController::class, 'update'])->name('artifacts.update');
    Route::delete('/artefatti/{artifact}', [App\Http\Controllers\Docente\ArtifactController::class, 'destroy'])->name('artifacts.destroy');
    Route::get('/artefatti/{artifact}/stato', [App\Http\Controllers\Docente\ArtifactController::class, 'status'])->name('artifacts.status');
    Route::post('/artefatti/{artifact}/rigenera', [App\Http\Controllers\Docente\ArtifactGenerationController::class, 'regenerate'])->name('artifacts.regenerate')->middleware('throttle:schola-generate');

    // Biblioteca docenti (pacchetto 9)
    Route::patch('/artefatti/{artifact}/condivisione', [App\Http\Controllers\Docente\ArtifactSharingController::class, 'update'])->name('artifacts.sharing');
    Route::get('/biblioteca', [App\Http\Controllers\Docente\TeacherLibraryController::class, 'index'])->name('biblioteca.index');
    Route::get('/biblioteca/{artifact}', [App\Http\Controllers\Docente\TeacherLibraryController::class, 'show'])->name('biblioteca.show');
    Route::post('/biblioteca/{artifact}/duplica', [App\Http\Controllers\Docente\TeacherLibraryController::class, 'fork'])->name('biblioteca.fork');

    // Pubblicazione su classe (pacchetto 6)
    Route::post('/artefatti/{artifact}/pubblica', [App\Http\Controllers\Docente\PublicationController::class, 'store'])->name('artifacts.publish');
    Route::get('/artefatti/{artifact}/pubblicazioni/stato', [App\Http\Controllers\Docente\PublicationController::class, 'status'])->name('artifacts.publications.status');
    Route::delete('/pubblicazioni/{publication}', [App\Http\Controllers\Docente\PublicationController::class, 'destroy'])->name('publications.destroy');
});

// ===== AREA SEGRETERIA SCOLASTICA (fase 2, P12) =====
// Gate school_admin + cambio password obbligatorio. Tutto scoped su school_id.
Route::prefix('scuola')->name('scuola.')->middleware(['school_admin', 'student.password'])->group(function () {
    Route::get('/', [App\Http\Controllers\Scuola\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/anagrafica', [App\Http\Controllers\Scuola\ProfileController::class, 'edit'])->name('anagrafica.edit');
    Route::patch('/anagrafica', [App\Http\Controllers\Scuola\ProfileController::class, 'update'])->name('anagrafica.update');

    // Docenti (P13): elenco + import CSV a gate (preview → commit → discard)
    Route::get('/docenti', [App\Http\Controllers\Scuola\TeacherController::class, 'index'])->name('docenti.index');
    Route::get('/docenti/aggiungi', [App\Http\Controllers\Scuola\TeacherController::class, 'create'])->name('docenti.create');
    Route::post('/docenti', [App\Http\Controllers\Scuola\TeacherController::class, 'store'])->name('docenti.store');
    Route::get('/docenti/import', [App\Http\Controllers\Scuola\TeacherImportController::class, 'create'])->name('docenti.import.create');
    Route::post('/docenti/import/preview', [App\Http\Controllers\Scuola\TeacherImportController::class, 'preview'])->name('docenti.import.preview');
    Route::post('/docenti/import/commit', [App\Http\Controllers\Scuola\TeacherImportController::class, 'commit'])->name('docenti.import.commit');
    Route::post('/docenti/import/{batch}/discard', [App\Http\Controllers\Scuola\TeacherImportController::class, 'discard'])->name('docenti.import.discard');
    // Modifica docente (P-segreteria): edit/update + reset password + attiva/disattiva,
    // scoped sulla propria scuola via ownTeacher()/assertSameSchool().
    Route::get('/docenti/{teacher}/modifica', [App\Http\Controllers\Scuola\TeacherController::class, 'edit'])->name('docenti.edit');
    Route::patch('/docenti/{teacher}', [App\Http\Controllers\Scuola\TeacherController::class, 'update'])->name('docenti.update');
    Route::post('/docenti/{teacher}/reset-password', [App\Http\Controllers\Scuola\TeacherController::class, 'resetPassword'])->name('docenti.reset-password');
    Route::patch('/docenti/{teacher}/stato', [App\Http\Controllers\Scuola\TeacherController::class, 'toggleActive'])->name('docenti.toggle');

    // Studenti (P14): elenco + import CSV a gate con credenziali duali
    Route::get('/studenti', [App\Http\Controllers\Scuola\StudentController::class, 'index'])->name('studenti.index');
    Route::get('/studenti/aggiungi', [App\Http\Controllers\Scuola\StudentController::class, 'create'])->name('studenti.create');
    Route::post('/studenti', [App\Http\Controllers\Scuola\StudentController::class, 'store'])->name('studenti.store');
    Route::get('/studenti/import', [App\Http\Controllers\Scuola\StudentImportController::class, 'create'])->name('studenti.import.create');
    Route::post('/studenti/import/preview', [App\Http\Controllers\Scuola\StudentImportController::class, 'preview'])->name('studenti.import.preview');
    Route::post('/studenti/import/commit', [App\Http\Controllers\Scuola\StudentImportController::class, 'commit'])->name('studenti.import.commit');
    Route::get('/studenti/import/{batch}/risultato', [App\Http\Controllers\Scuola\StudentImportController::class, 'result'])->name('studenti.import.result');
    Route::get('/studenti/import/{batch}/credenziali.csv', [App\Http\Controllers\Scuola\StudentImportController::class, 'credentialsDownload'])->name('studenti.import.credentials');
    Route::post('/studenti/import/{batch}/discard', [App\Http\Controllers\Scuola\StudentImportController::class, 'discard'])->name('studenti.import.discard');

    // Classi e cattedre (P15): gestione segreteria
    Route::get('/classi', [App\Http\Controllers\Scuola\ClassController::class, 'index'])->name('classi.index');
    Route::get('/classi/crea', [App\Http\Controllers\Scuola\ClassController::class, 'create'])->name('classi.create');
    Route::post('/classi', [App\Http\Controllers\Scuola\ClassController::class, 'store'])->name('classi.store');
    Route::get('/classi/{class}', [App\Http\Controllers\Scuola\ClassController::class, 'show'])->name('classi.show');
    Route::patch('/classi/{class}', [App\Http\Controllers\Scuola\ClassController::class, 'update'])->name('classi.update');
    Route::post('/classi/{class}/studenti', [App\Http\Controllers\Scuola\ClassController::class, 'assignStudents'])->name('classi.students');
    Route::post('/classi/{class}/cattedre', [App\Http\Controllers\Scuola\ClassController::class, 'assignCattedra'])->name('classi.cattedre.store');
    Route::delete('/cattedre/{assignment}', [App\Http\Controllers\Scuola\ClassController::class, 'destroyCattedra'])->name('cattedre.destroy');

    // GDPR (P16): DPA, export dati scuola, audit import
    Route::get('/privacy', [App\Http\Controllers\Scuola\PrivacyController::class, 'index'])->name('privacy.index');
    Route::post('/privacy/dpa', [App\Http\Controllers\Scuola\PrivacyController::class, 'markDpa'])->name('privacy.dpa');
    Route::post('/privacy/export', [App\Http\Controllers\Scuola\PrivacyController::class, 'export'])->name('privacy.export');
    Route::get('/privacy/export/download', [App\Http\Controllers\Scuola\PrivacyController::class, 'download'])->name('privacy.export.download');
});

// Logo scuola da storage privato — accessibile a tutti gli utenti della scuola
// (segreteria/docenti/studenti) e al platform admin, quindi fuori dal gate
// school_admin ma sotto student.auth.
Route::get('/branding/scuola/{school}/logo', [App\Http\Controllers\Scuola\BrandingController::class, 'logo'])
    ->middleware('student.auth')->name('scuola.logo');

// ===== AREA ADMIN OFFICINA =====
Route::prefix('admin')->name('admin.')->middleware(['admin.auth'])->group(function () {
    Route::get('/', [App\Http\Controllers\Admin\AdminDashboardController::class, 'index'])->name('dashboard');

    // Scuole (fase 2, P11) — il platform admin è l'unico che attraversa le scuole.
    Route::get('scuole', [App\Http\Controllers\Admin\SchoolController::class, 'index'])->name('scuole.index');
    Route::get('scuole/crea', [App\Http\Controllers\Admin\SchoolController::class, 'create'])->name('scuole.create');
    Route::post('scuole', [App\Http\Controllers\Admin\SchoolController::class, 'store'])->name('scuole.store');
    Route::get('scuole/{school}', [App\Http\Controllers\Admin\SchoolController::class, 'show'])->name('scuole.show');
    Route::patch('scuole/{school}', [App\Http\Controllers\Admin\SchoolController::class, 'update'])->name('scuole.update');
    Route::post('scuole/{school}/segreteria', [App\Http\Controllers\Admin\SchoolController::class, 'nominateAdmin'])->name('scuole.nominate');
    Route::post('scuole/{school}/segreteria/{admin}/reset', [App\Http\Controllers\Admin\SchoolController::class, 'resetAdminPassword'])->name('scuole.segreteria.reset');
    Route::post('scuole/{school}/segreteria/{admin}/reinvia', [App\Http\Controllers\Admin\SchoolController::class, 'resendInvite'])->name('scuole.segreteria.resend');
    Route::patch('scuole/{school}/segreteria/{admin}/stato', [App\Http\Controllers\Admin\SchoolController::class, 'toggleAdminActive'])->name('scuole.segreteria.toggle');

    Route::get('courses/ingest', [App\Http\Controllers\Admin\CourseIngestController::class, 'form'])->name('courses.ingest.form');
    Route::post('courses/ingest/parse', [App\Http\Controllers\Admin\CourseIngestController::class, 'parse'])->name('courses.ingest.parse');
    Route::get('courses/ingest/preview', [App\Http\Controllers\Admin\CourseIngestController::class, 'preview'])->name('courses.ingest.preview');
    Route::post('courses/ingest/confirm', [App\Http\Controllers\Admin\CourseIngestController::class, 'confirm'])->name('courses.ingest.confirm');
    Route::post('courses/ingest/cancel', [App\Http\Controllers\Admin\CourseIngestController::class, 'cancel'])->name('courses.ingest.cancel');
    Route::get('courses/ingest/processing', [App\Http\Controllers\Admin\CourseIngestController::class, 'processing'])->name('courses.ingest.processing');
    Route::get('courses/ingest/status', [App\Http\Controllers\Admin\CourseIngestController::class, 'status'])->name('courses.ingest.status');

    Route::resource('courses', App\Http\Controllers\Admin\CourseController::class);
    Route::resource('courses.modules', App\Http\Controllers\Admin\ModuleController::class);
    Route::resource('courses.modules.materials', App\Http\Controllers\Admin\MaterialController::class);

    // Mappe mentali moduli (Claude API generated, markmap-compatible)
    Route::post('courses/{course}/modules/{module}/mindmap/generate', [App\Http\Controllers\Admin\ModuleMindMapController::class, 'generate'])->name('courses.modules.mindmap.generate');
    Route::patch('courses/{course}/modules/{module}/mindmap', [App\Http\Controllers\Admin\ModuleMindMapController::class, 'update'])->name('courses.modules.mindmap.update');

    // Presentazione .pptx modulo (P28) — generatore condiviso, pattern async
    Route::post('courses/{course}/modules/{module}/presentation/generate', [App\Http\Controllers\Admin\ModulePresentationController::class, 'generate'])->name('courses.modules.presentation.generate');
    Route::post('courses/{course}/modules/{module}/presentation/regenerate', [App\Http\Controllers\Admin\ModulePresentationController::class, 'regenerate'])->name('courses.modules.presentation.regenerate');
    Route::post('courses/{course}/modules/{module}/presentation/edit', [App\Http\Controllers\Admin\ModulePresentationController::class, 'edit'])->name('courses.modules.presentation.edit');
    Route::get('courses/{course}/modules/{module}/presentation/status', [App\Http\Controllers\Admin\ModulePresentationController::class, 'status'])->name('courses.modules.presentation.status');
    Route::get('courses/{course}/modules/{module}/presentation/download', [App\Http\Controllers\Admin\ModulePresentationController::class, 'download'])->name('courses.modules.presentation.download');
    Route::get('courses/{course}/modules/{module}/presentation/slide/{n}', [App\Http\Controllers\Admin\ModulePresentationController::class, 'previewImage'])->whereNumber('n')->name('courses.modules.presentation.preview');

    // Documento PDF modulo (P29 Fase 1) — renderer brandizzato, pattern async + stale
    Route::post('courses/{course}/modules/{module}/document/generate', [App\Http\Controllers\Admin\ModuleDocumentController::class, 'generate'])->name('courses.modules.document.generate');
    Route::post('courses/{course}/modules/{module}/document/regenerate', [App\Http\Controllers\Admin\ModuleDocumentController::class, 'regenerate'])->name('courses.modules.document.regenerate');
    Route::get('courses/{course}/modules/{module}/document/status', [App\Http\Controllers\Admin\ModuleDocumentController::class, 'status'])->name('courses.modules.document.status');
    Route::get('courses/{course}/modules/{module}/document/download', [App\Http\Controllers\Admin\ModuleDocumentController::class, 'download'])->name('courses.modules.document.download');

    // Documento PDF dell'intero corso (P29 Fase 2) — hash aggregato, pattern async + stale
    Route::post('courses/{course}/document/generate', [App\Http\Controllers\Admin\CourseDocumentController::class, 'generate'])->name('courses.document.generate');
    Route::post('courses/{course}/document/regenerate', [App\Http\Controllers\Admin\CourseDocumentController::class, 'regenerate'])->name('courses.document.regenerate');
    Route::get('courses/{course}/document/status', [App\Http\Controllers\Admin\CourseDocumentController::class, 'status'])->name('courses.document.status');
    Route::get('courses/{course}/document/download', [App\Http\Controllers\Admin\CourseDocumentController::class, 'download'])->name('courses.document.download');

    // Mappe concettuali (admin) — livello modulo (1 per modulo) + livello corso (1 globale opzionale)
    Route::resource('courses.concept-maps', App\Http\Controllers\Admin\CourseConceptMapController::class);
    Route::post('courses/{course}/concept-maps/{concept_map}/generate', [App\Http\Controllers\Admin\CourseConceptMapController::class, 'generate'])->name('courses.concept-maps.generate');
    Route::post('courses/{course}/concept-maps/auto-create', [App\Http\Controllers\Admin\CourseConceptMapController::class, 'autoCreate'])->name('courses.concept-maps.auto-create');

    // P25.3b — Coda HITL proposte di aggiornamento corsi (additivo: non tocca i corsi).
    Route::get('aggiornamenti', [App\Http\Controllers\Admin\FreshnessProposalController::class, 'index'])->name('freshness.proposals.index');
    Route::get('aggiornamenti/stato-run', [App\Http\Controllers\Admin\FreshnessProposalController::class, 'runsStatus'])->name('freshness.proposals.runs-status');
    Route::patch('aggiornamenti/run/{run}/archivia', [App\Http\Controllers\Admin\FreshnessProposalController::class, 'dismissRun'])->name('freshness.proposals.run-dismiss');
    Route::post('aggiornamenti/storico/pulisci', [App\Http\Controllers\Admin\FreshnessProposalController::class, 'clearRuns'])->name('freshness.proposals.runs-clear');
    Route::post('aggiornamenti/run', [App\Http\Controllers\Admin\FreshnessProposalController::class, 'run'])->name('freshness.proposals.run');
    Route::post('aggiornamenti/bulk', [App\Http\Controllers\Admin\FreshnessProposalController::class, 'bulk'])->name('freshness.proposals.bulk');
    Route::post('aggiornamenti/{course}/cadenza', [App\Http\Controllers\Admin\FreshnessProposalController::class, 'setCadence'])->name('freshness.proposals.cadence');
    Route::post('aggiornamenti/{course}/audience', [App\Http\Controllers\Admin\FreshnessProposalController::class, 'setAudience'])->name('freshness.proposals.audience');
    Route::post('aggiornamenti/{course}/applica', [App\Http\Controllers\Admin\FreshnessProposalController::class, 'apply'])->name('freshness.proposals.apply');
    Route::post('aggiornamenti/{course}/rollback', [App\Http\Controllers\Admin\FreshnessProposalController::class, 'rollback'])->name('freshness.proposals.rollback');
    Route::patch('aggiornamenti/{proposal}/approva', [App\Http\Controllers\Admin\FreshnessProposalController::class, 'approve'])->name('freshness.proposals.approve');
    Route::patch('aggiornamenti/{proposal}/rifiuta', [App\Http\Controllers\Admin\FreshnessProposalController::class, 'reject'])->name('freshness.proposals.reject');
    Route::patch('aggiornamenti/{proposal}/conferma', [App\Http\Controllers\Admin\FreshnessProposalController::class, 'confirm'])->name('freshness.proposals.confirm');

    // P26 Fase 0 — Registro fonti attendibili (gated da config services.p26.enabled nel controller).
    Route::get('fonti', [App\Http\Controllers\Admin\TrustedSourceController::class, 'index'])->name('sources.index');
    Route::post('fonti', [App\Http\Controllers\Admin\TrustedSourceController::class, 'store'])->name('sources.store');
    Route::post('fonti/proponi', [App\Http\Controllers\Admin\TrustedSourceController::class, 'suggest'])->name('sources.suggest');
    Route::patch('fonti/{source}/approva', [App\Http\Controllers\Admin\TrustedSourceController::class, 'approve'])->name('sources.approve');
    Route::patch('fonti/{source}/rifiuta', [App\Http\Controllers\Admin\TrustedSourceController::class, 'reject'])->name('sources.reject');
    Route::delete('fonti/{source}', [App\Http\Controllers\Admin\TrustedSourceController::class, 'destroy'])->name('sources.destroy');

    // P26 Fase A — Scout di copertura (gated da config services.p26.enabled nel controller).
    Route::get('copertura', [App\Http\Controllers\Admin\CoverageGapController::class, 'index'])->name('coverage.index');
    Route::get('copertura/{course}', [App\Http\Controllers\Admin\CoverageGapController::class, 'show'])->name('coverage.show');
    Route::post('copertura/{course}/topic', [App\Http\Controllers\Admin\CoverageGapController::class, 'setTopic'])->name('coverage.topic');
    Route::post('copertura/{course}/topic/suggerisci', [App\Http\Controllers\Admin\CoverageGapController::class, 'suggestTopic'])->name('coverage.topic.suggest');
    Route::post('copertura/{course}/topics', [App\Http\Controllers\Admin\CoverageGapController::class, 'setTopics'])->name('coverage.topics');
    Route::post('copertura/{course}/topics/suggerisci', [App\Http\Controllers\Admin\CoverageGapController::class, 'suggestTopicsAction'])->name('coverage.topics.suggest');
    Route::post('copertura/{course}/analizza', [App\Http\Controllers\Admin\CoverageGapController::class, 'analyze'])->name('coverage.analyze');
    Route::patch('copertura/gap/{gap}/accetta', [App\Http\Controllers\Admin\CoverageGapController::class, 'accept'])->name('coverage.accept');
    Route::patch('copertura/gap/{gap}/scarta', [App\Http\Controllers\Admin\CoverageGapController::class, 'dismiss'])->name('coverage.dismiss');

    // P26 Fase B — Compose bozze (gated). NON inserisce nulla nei corsi.
    Route::post('copertura/gap/{gap}/genera', [App\Http\Controllers\Admin\CoverageGapController::class, 'generate'])->name('coverage.generate');
    Route::get('copertura/gap/{gap}/bozza', [App\Http\Controllers\Admin\CoverageGapController::class, 'draftView'])->name('coverage.draft');
    Route::put('copertura/bozza/{draft}', [App\Http\Controllers\Admin\CoverageGapController::class, 'updateDraft'])->name('coverage.draft.update');
    Route::patch('copertura/bozza/{draft}/approva', [App\Http\Controllers\Admin\CoverageGapController::class, 'approveDraft'])->name('coverage.draft.approve');
    Route::patch('copertura/bozza/{draft}/scarta', [App\Http\Controllers\Admin\CoverageGapController::class, 'discardDraft'])->name('coverage.draft.discard');

    // P26 Fasi C+D — Place (posizione, HITL) + Insert/Revert (append-only, reversibile).
    Route::post('copertura/gap/{gap}/posizione/proponi', [App\Http\Controllers\Admin\CoverageGapController::class, 'proposePlace'])->name('coverage.place.propose');
    Route::put('copertura/gap/{gap}/posizione', [App\Http\Controllers\Admin\CoverageGapController::class, 'confirmPlace'])->name('coverage.place.confirm');
    Route::post('copertura/gap/{gap}/inserisci', [App\Http\Controllers\Admin\CoverageGapController::class, 'insert'])->name('coverage.insert');
    Route::post('copertura/inserimento/{insertion}/annulla', [App\Http\Controllers\Admin\CoverageGapController::class, 'revert'])->name('coverage.revert');

    Route::prefix('courses/{course}/instructor-materials')
        ->name('courses.instructor-materials.')
        ->group(function () {
            Route::post('/', [App\Http\Controllers\Admin\InstructorMaterialController::class, 'store'])->name('store');
            Route::put('/{material}', [App\Http\Controllers\Admin\InstructorMaterialController::class, 'update'])->name('update');
            Route::post('/{material}/regenerate', [App\Http\Controllers\Admin\InstructorMaterialController::class, 'regenerate'])->name('regenerate');
            Route::delete('/{material}', [App\Http\Controllers\Admin\InstructorMaterialController::class, 'destroy'])->name('destroy');
            Route::get('/{material}/sections', [App\Http\Controllers\Admin\InstructorMaterialController::class, 'manageSections'])->name('sections');
            Route::put('/{material}/sections', [App\Http\Controllers\Admin\InstructorMaterialController::class, 'updateSections'])->name('sections.update');
            Route::post('/{material}/sections/reset', [App\Http\Controllers\Admin\InstructorMaterialController::class, 'resetSections'])->name('sections.reset');
        });

    Route::get('knowledge-base', [App\Http\Controllers\Admin\KnowledgeBaseController::class, 'index'])->name('knowledge-base.index');

    // Soft delete management — DEVONO stare prima del Route::resource('students')
    // perché students/trashed matcha altrimenti students/{student} parametrico.
    Route::get('students/trashed', [App\Http\Controllers\Admin\StudentController::class, 'trashed'])->name('students.trashed');
    Route::patch('students/{id}/restore', [App\Http\Controllers\Admin\StudentController::class, 'restore'])->name('students.restore');
    Route::delete('students/{id}/force-delete', [App\Http\Controllers\Admin\StudentController::class, 'forceDestroy'])->name('students.force-delete');

    Route::resource('students', App\Http\Controllers\Admin\StudentController::class);
    Route::post('students/{student}/courses', [App\Http\Controllers\Admin\StudentController::class, 'assignCourse'])->name('students.assign-course');
    Route::delete('students/{student}/courses/{course}', [App\Http\Controllers\Admin\StudentController::class, 'removeCourse'])->name('students.remove-course');
    Route::patch('students/{student}/courses/{course}/instructor', [App\Http\Controllers\Admin\StudentController::class, 'updateCourseInstructor'])->name('students.update-course-instructor');
    Route::post('students/{student}/send-credentials', [App\Http\Controllers\Admin\StudentController::class, 'sendCredentials'])->name('students.send-credentials');
    Route::patch('students/{student}/system-role', [App\Http\Controllers\Admin\StudentController::class, 'updateSystemRole'])->name('students.update-system-role');

    Route::get('instructors',                                  [App\Http\Controllers\Admin\InstructorController::class, 'index'])->name('instructors.index');
    // create/store PRIMA del wildcard {instructor} per non far matchare "create" come id.
    Route::get('instructors/create',                           [App\Http\Controllers\Admin\InstructorController::class, 'create'])->name('instructors.create');
    Route::post('instructors',                                 [App\Http\Controllers\Admin\InstructorController::class, 'store'])->name('instructors.store');
    Route::get('instructors/{instructor}',                     [App\Http\Controllers\Admin\InstructorController::class, 'show'])->name('instructors.show');
    Route::get('instructors/{instructor}/edit',                [App\Http\Controllers\Admin\InstructorController::class, 'edit'])->name('instructors.edit');
    Route::put('instructors/{instructor}',                     [App\Http\Controllers\Admin\InstructorController::class, 'update'])->name('instructors.update');
    Route::post('instructors/{instructor}/courses',            [App\Http\Controllers\Admin\InstructorController::class, 'attachCourse'])->name('instructors.attach-course');
    Route::delete('instructors/{instructor}/courses/{course}', [App\Http\Controllers\Admin\InstructorController::class, 'detachCourse'])->name('instructors.detach-course');
    Route::get('courses/{course}/instructors',                 [App\Http\Controllers\Admin\InstructorController::class, 'forCourse'])->name('courses.instructors');

    Route::resource('quizzes', App\Http\Controllers\Admin\QuizController::class);
    Route::resource('quizzes.questions', App\Http\Controllers\Admin\QuizQuestionController::class);
    Route::get('quizzes/{quiz}/results', [App\Http\Controllers\Admin\QuizController::class, 'results'])->name('quizzes.results');
    Route::post('quizzes/{quiz}/grant-attempt', [App\Http\Controllers\Admin\QuizController::class, 'grantAttempt'])->name('quizzes.grant-attempt');

    Route::get('rag', [App\Http\Controllers\Admin\RagController::class, 'index'])->name('rag.index');
    Route::post('rag/upload', [App\Http\Controllers\Admin\RagController::class, 'upload'])->name('rag.upload');
    Route::delete('rag/{document}', [App\Http\Controllers\Admin\RagController::class, 'destroy'])->name('rag.destroy');

    Route::get('analytics', [App\Http\Controllers\Admin\AnalyticsController::class, 'index'])->name('analytics');
    Route::post('analytics/send-reminders', [App\Http\Controllers\Admin\AnalyticsController::class, 'sendReminders'])->name('analytics.send-reminders');
    Route::post('analytics/send-reminder/{student}', [App\Http\Controllers\Admin\AnalyticsController::class, 'sendReminder'])->name('analytics.send-reminder');

    Route::post('upload-image', [App\Http\Controllers\Admin\AdminDashboardController::class, 'uploadImage'])->name('upload-image');
    Route::post('courses/{course}/generate-quiz', [App\Http\Controllers\Admin\CourseController::class, 'generateQuiz'])->name('courses.generate-quiz');

    // Firma digitale certificati — solo legale rappresentante.
    // Le route batch/* sono dichiarate PRIMA di {certificate}/... per
    // evitare che Laravel interpreti "batch" come model binding di Certificate.
    Route::middleware(['legal_representative'])
        ->prefix('certificates/signatures')
        ->name('certificates.signatures.')
        ->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\CertificateSignatureController::class, 'index'])
                ->name('index');

            Route::get('/batch/download', [App\Http\Controllers\Admin\CertificateSignatureController::class, 'downloadBatch'])
                ->name('batch.download');

            Route::post('/batch/upload', [App\Http\Controllers\Admin\CertificateSignatureController::class, 'uploadBatch'])
                ->name('batch.upload');

            Route::get('/{certificate}/download', [App\Http\Controllers\Admin\CertificateSignatureController::class, 'download'])
                ->name('download');

            Route::post('/{certificate}/upload', [App\Http\Controllers\Admin\CertificateSignatureController::class, 'upload'])
                ->name('upload');
        });

    Route::get('admins',                       [App\Http\Controllers\Admin\AdminAccountController::class, 'index'])->name('admins.index');
    Route::post('admins',                      [App\Http\Controllers\Admin\AdminAccountController::class, 'store'])->name('admins.store');
    Route::patch('admins/{admin}',             [App\Http\Controllers\Admin\AdminAccountController::class, 'update'])->name('admins.update');
    Route::patch('admins/{admin}/password',    [App\Http\Controllers\Admin\AdminAccountController::class, 'password'])->name('admins.password');
    Route::patch('admins/{admin}/toggle',      [App\Http\Controllers\Admin\AdminAccountController::class, 'toggle'])->name('admins.toggle');
    Route::patch('admins/{admin}/signature',   [App\Http\Controllers\Admin\AdminAccountController::class, 'signature'])->name('admins.signature');

    Route::get('settings',                     [App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings',                     [App\Http\Controllers\Admin\SettingsController::class, 'update'])->name('settings.update');
    Route::post('settings/test-mail',          [App\Http\Controllers\Admin\SettingsController::class, 'testMail'])->name('settings.test-mail');

    Route::get('/login', [App\Http\Controllers\Admin\AdminAuthController::class, 'showLogin'])->name('login')->withoutMiddleware(['admin.auth']);
    Route::post('/login', [App\Http\Controllers\Admin\AdminAuthController::class, 'login'])->middleware('throttle:login')->name('login.post')->withoutMiddleware(['admin.auth']);
    Route::post('/logout', [App\Http\Controllers\Admin\AdminAuthController::class, 'logout'])->name('logout');

    // 2FA management (admin loggato richiesto, group ereditario)
    Route::prefix('security/2fa')->name('security.2fa.')->group(function () {
        Route::get('/',         [App\Http\Controllers\Admin\TwoFactorController::class, 'show'])->name('show');
        Route::post('/enable',  [App\Http\Controllers\Admin\TwoFactorController::class, 'enable'])->name('enable');
        Route::post('/confirm', [App\Http\Controllers\Admin\TwoFactorController::class, 'confirm'])->name('confirm');
        Route::post('/disable', [App\Http\Controllers\Admin\TwoFactorController::class, 'disable'])->name('disable');
        Route::post('/recovery-codes', [App\Http\Controllers\Admin\TwoFactorController::class, 'regenerateRecoveryCodes'])->name('recovery.regenerate');
    });
});

// 2FA challenge: l'admin ha password OK ma non e' ancora "logged_in".
// Fuori dal middleware admin.auth (sennò redirect a login infinito).
// Throttle 5/min anti brute-force sul verify.
Route::get('/admin/2fa/challenge', [App\Http\Controllers\Admin\TwoFactorChallengeController::class, 'show'])->name('admin.2fa.challenge');
Route::post('/admin/2fa/verify', [App\Http\Controllers\Admin\TwoFactorChallengeController::class, 'verify'])
    ->middleware('throttle:5,1')
    ->name('admin.2fa.verify');
