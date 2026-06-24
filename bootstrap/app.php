<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Header di sicurezza su tutte le risposte del gruppo web.
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->alias([
            'student.auth' => \App\Http\Middleware\StudentAuth::class,
            'student.password' => \App\Http\Middleware\StudentMustChangePassword::class,
            'professor' => \App\Http\Middleware\ProfessorAuth::class,
            'school_admin' => \App\Http\Middleware\SchoolAdminAuth::class,
            'admin.auth' => \App\Http\Middleware\AdminAuth::class,
            'demo.restrictions' => \App\Http\Middleware\DemoRestrictions::class,
            'legal_representative' => \App\Http\Middleware\EnsureLegalRepresentative::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
