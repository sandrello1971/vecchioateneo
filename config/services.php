<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'brevo' => [
        'key' => env('BREVO_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'videoai' => [
        'url' => env('VIDEO_AI_URL', 'http://127.0.0.1:8001'),
        // Token interno inviato su OGNI chiamata a videoai (header X-Internal-Token).
        // Deve combaciare con INTERNAL_API_TOKEN lato videoai.
        'token' => env('VIDEOAI_INTERNAL_TOKEN', ''),
        // Polling trascrizione audio/youtube (Schola pacchetto 4a/4b)
        'poll_interval' => (int) env('VIDEOAI_POLL_INTERVAL', 3),
        'poll_max_attempts' => (int) env('VIDEOAI_POLL_MAX_ATTEMPTS', 200),
    ],

    // Embedding per il RAG vettoriale Schola (pre-pacchetto 6). Il servizio è
    // videoai (/api/embeddings); le dimensioni DEVONO combaciare con la
    // colonna vector(D) di documents_rag e col modello scelto.
    'embeddings' => [
        'url' => env('EMBEDDINGS_URL', env('VIDEO_AI_URL', 'http://127.0.0.1:8001')),
        'model' => env('EMBEDDINGS_MODEL', 'paraphrase-multilingual-mpnet-base-v2'),
        'dimensions' => (int) env('EMBEDDINGS_DIMENSIONS', 768),
        'batch' => (int) env('EMBEDDINGS_BATCH', 128),
        'timeout' => (int) env('EMBEDDINGS_TIMEOUT', 60),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        // Vision (OCR/trascrizione foto e PDF scansionati) — Schola pacchetto 4a
        'vision_model' => env('ANTHROPIC_VISION_MODEL', 'claude-sonnet-4-5'),
        'vision_max_tokens' => (int) env('ANTHROPIC_VISION_MAX_TOKENS', 4000),
        // P25 Course Freshness Agent — modelli configurabili da .env.
        // Estrazione affermazioni (Fase 1) + generazione proposte (Fase 3): Sonnet.
        'freshness_extract_model' => env('FRESHNESS_EXTRACT_MODEL', 'claude-sonnet-4-6'),
        // Verifica con web_search (Fase 2): Opus, qualità sui verdetti (costo superiore).
        'freshness_verify_model' => env('FRESHNESS_VERIFY_MODEL', 'claude-opus-4-8'),
    ],

    // Generazione .pptx (Schola P21): python-pptx nel venv condiviso.
    'pptx' => [
        'python' => env('PPTX_PYTHON', '/home/noscite/venv/bin/python'),
        'model'  => env('PPTX_MODEL', 'claude-sonnet-4-6'),
    ],

    // P26 "Gap & Compose": opt-in finché non è pronto. Nasconde /admin/fonti + endpoint.
    'p26' => [
        'enabled' => env('P26_ENABLED', false),
    ],

];
