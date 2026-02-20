<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$argv = $_SERVER['argv'] ?? [];
$hasTestingFlag = in_array('--env=testing', $argv, true)
    || (
        ($envIndex = array_search('--env', $argv, true)) !== false
        && (($argv[$envIndex + 1] ?? null) === 'testing')
    );
$appEnv = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: null;

if ($appEnv === 'testing' || $hasTestingFlag) {
    // In containerized dev, DB_DATABASE is injected as an OS env var and would
    // override .env.testing; clear it so testing always resolves its own database.
    putenv('DB_DATABASE');
    unset($_ENV['DB_DATABASE'], $_SERVER['DB_DATABASE']);
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
