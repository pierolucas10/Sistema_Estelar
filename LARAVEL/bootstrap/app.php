<?php

use Illuminate\Console\Scheduling\Schedule;
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
        $middleware->alias([
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('reuniones:expirar-recordatorios')->everyMinute()->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Token CSRF vencido (pestaña abierta mucho tiempo, cookies viejas): en vez de la pantalla
        // «419 Page Expired», se vuelve al formulario con un aviso y se puede intentar de nuevo.
        // Laravel convierte TokenMismatchException en un HttpException 419 antes de llegar aquí.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }

            $destino = $request->is('login') || !auth()->check()
                ? redirect()->route('login')
                : redirect()->back();

            return $destino
                ->withInput($request->except(['password', 'password_confirmation', '_token']))
                ->with('error', 'La sesión expiró por inactividad. Vuelve a intentarlo.')
                ->withErrors(['email' => 'La sesión expiró por inactividad. Vuelve a intentarlo.']);
        });
    })->create();
