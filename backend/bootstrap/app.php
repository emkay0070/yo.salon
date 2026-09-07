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
    ->withMiddleware(function (Middleware $middleware): void {
        // Handle CORS headers for all requests (required for frontend on port 3000)
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);

        $middleware->alias([
            'auth.any' => \App\Http\Middleware\AuthenticateAnyActor::class,
            'salon.context' => \App\Http\Middleware\ResolveSalonContext::class,
            'portal.context' => \App\Http\Middleware\ResolvePortalContext::class,
            'track.views' => \App\Http\Middleware\TrackViewCount::class,
            'specialist.capability' => \App\Http\Middleware\SpecialistCapabilityMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
