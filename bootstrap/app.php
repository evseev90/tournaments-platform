<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Support\DomainErrorTranslator;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Tournaments\Shared\Domain\DomainException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [HandleInertiaRequests::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Нарушение доменного правила — это ошибка ввода пользователя, а не сбой: возвращаем на форму с текстом.
        $exceptions->render(fn (DomainException $e, Request $request) => $request->expectsJson() && ! $request->header('X-Inertia')
            ? response()->json(['message' => DomainErrorTranslator::translate($e)], 422)
            : back()->withErrors(['domain' => DomainErrorTranslator::translate($e)]));
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
