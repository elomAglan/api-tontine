<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        
        // 1. LA BONNE MÉTHODE : Force le JSON pour toutes les erreurs
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return true; 
        });

        // 2. Log propre dans la console (sans le track)
        $exceptions->reportable(function (Throwable $e) {
            $date = now()->format('Y-m-d H:i:s');
            $path = request()->getPathInfo() ?? '/';
            $message = $e->getMessage();

            echo "\n  $date $path " . str_repeat('.', 20) . " ERROR: $message\n";

            return false; // Bloque le gros bloc dans le fichier de log
        });

    })->create();