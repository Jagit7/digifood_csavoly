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
        then: function (): void {
            \Illuminate\Support\Facades\Route::middleware('web')
                ->group(base_path('routes/parent.php'));

            \Illuminate\Support\Facades\Route::middleware('web')
                ->group(base_path('routes/employee.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('szulo') || $request->is('szulo/*')) {
                return route('parent.login', [], false);
            }

            if ($request->is('dolgozo') || $request->is('dolgozo/*')) {
                return route('employee.login', [], false);
            }

            return route('auth.login', [], false);
        });

        $middleware->redirectUsersTo(function ($request) {
            return match ($request->user()?->role) {
                \App\Models\User::ROLE_SUPER_ADMIN => route('dashboard.superadmin', [], false),
                \App\Models\User::ROLE_INSTITUTION_ADMIN,
                \App\Models\User::ROLE_INSTITUTION_SECRETARY,
                \App\Models\User::ROLE_KITCHEN,
                \App\Models\User::ROLE_MUNICIPALITY => route('dashboard.institution.home', [], false),
                \App\Models\User::ROLE_MEAL_KIOSK => route('kiosk.show', [], false),
                \App\Models\User::ROLE_PARENT => route('parent.dashboard', [], false),
                \App\Models\User::ROLE_EMPLOYEE => route('employee.dashboard', [], false),
                default => route('home', [], false),
            };
        });

        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'institution-context' => \App\Http\Middleware\EnsureInstitutionContext::class,
            'barcode-entry-enabled' => \App\Http\Middleware\EnsureBarcodeEntryEnabled::class,
            'parent' => \App\Http\Middleware\EnsureParentAccess::class,
            'employee' => \App\Http\Middleware\EnsureEmployeeAccess::class,
            'kiosk-device-auth' => \App\Http\Middleware\KioskDeviceAutoLogin::class,
        ]);

        // A kártyás fizetési szolgáltatók (pl. CIB Bank) szerver-szerver
        // visszahívása (callback) nem böngésző-munkamenetből érkezik, ezért
        // nem rendelkezik Laravel CSRF-tokennel - a hitelesítést a
        // szolgáltató saját aláírás-ellenőrzése adja (lásd
        // CibCardPaymentGateway::verifyAndParseCallback()).
        $middleware->validateCsrfTokens(except: [
            'payments/card/*/callback',
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('digifood:kitchen-notifications:dispatch')
            ->everyMinute()
            ->withoutOverlapping();
        $schedule->command('digifood:payment-period-notifications:dispatch')
            ->everyMinute()
            ->withoutOverlapping();
        $schedule->command('digifood:daily-attendance-email:dispatch')
            ->everyMinute()
            ->withoutOverlapping();
        $schedule->command('digifood:saas-billing-summary:dispatch')
            ->dailyAt('08:05')
            ->withoutOverlapping();
        $schedule->command('digifood:billingo-invoices:sync')
            ->dailyAt('02:30')
            ->withoutOverlapping()
            ->timezone(config('app.timezone'))
            ->appendOutputTo(storage_path('logs/billingo-invoice-sync.log'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
