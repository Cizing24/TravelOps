<?php

use App\Domain\RuleViolation;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->preventRequestForgery(except: ['webhooks/mock', 'webhooks/mock-suppliers/*', 'webhooks/mock-refunds']);
        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['code']);
        $exceptions->dontReport([RuleViolation::class]);
        $exceptions->render(function (RuleViolation $error, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $error->getMessage(), 'code' => $error->reason], $error->status);
            }

            return response()->view('problem', ['message' => $error->getMessage()], $error->status);
        });
    })->create();
