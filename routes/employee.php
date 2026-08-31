<?php

use App\Http\Controllers\EmployeePortal\Auth\EmployeeAuthenticatedSessionController;
use App\Http\Controllers\EmployeePortal\Auth\EmployeeAccountActivationController;
use App\Http\Controllers\EmployeePortal\EmployeeAccountController;
use App\Http\Controllers\EmployeePortal\EmployeeDashboardController;
use App\Http\Controllers\EmployeePortal\EmployeeInvoiceController;
use App\Http\Controllers\EmployeePortal\EmployeeLegalPageController;
use App\Http\Controllers\EmployeePortal\EmployeeMealCancellationController;
use App\Http\Controllers\EmployeePortal\EmployeeMenuChoiceController;
use App\Http\Controllers\EmployeePortal\EmployeePaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('dolgozo')
    ->as('employee.')
    ->group(function () {
        Route::get('/', function () {
            return auth()->check()
                ? redirect(route('employee.dashboard', [], false))
                : redirect(route('employee.login', [], false));
        })->name('home');

        Route::middleware('guest')->group(function () {
            Route::get('/login', [EmployeeAuthenticatedSessionController::class, 'create'])->name('login');
            Route::post('/login', [EmployeeAuthenticatedSessionController::class, 'store'])->name('login.store');
            Route::get('/aktivalas', [EmployeeAccountActivationController::class, 'create'])->name('activation.create');
            Route::post('/aktivalas', [EmployeeAccountActivationController::class, 'send'])
                ->middleware('throttle:5,1')
                ->name('activation.send');
            Route::get('/aktivalas/{token}', [EmployeeAccountActivationController::class, 'show'])->name('activation.show');
            Route::post('/aktivalas/{token}', [EmployeeAccountActivationController::class, 'store'])->name('activation.store');
        });

        Route::middleware(['auth', 'employee'])->group(function () {
            Route::post('/logout', [EmployeeAuthenticatedSessionController::class, 'destroy'])->name('logout');

            Route::get('/vezerlopult', EmployeeDashboardController::class)->name('dashboard');

            Route::get('/etkezesek-es-lemondasok', [EmployeeMealCancellationController::class, 'index'])
                ->name('meal-cancellations');
            Route::get('/etkezesek-es-lemondasok/nap/{date}', [EmployeeMealCancellationController::class, 'day'])
                ->name('meal-cancellations.day');
            Route::post('/etkezesek-es-lemondasok/lemondas', [EmployeeMealCancellationController::class, 'store'])
                ->name('meal-cancellations.store');
            Route::post('/etkezesek-es-lemondasok/visszaallitas', [EmployeeMealCancellationController::class, 'restore'])
                ->name('meal-cancellations.restore');

            Route::get('/menuvalasztas', [EmployeeMenuChoiceController::class, 'index'])
                ->name('menu-choices.index');
            Route::put('/menuvalasztas/{employee}', [EmployeeMenuChoiceController::class, 'update'])
                ->name('menu-choices.update');

            Route::get('/szamlak', [EmployeeInvoiceController::class, 'index'])
                ->name('invoices');
            Route::get('/szamlak/{statement}/letoltes', [EmployeeInvoiceController::class, 'download'])
                ->name('invoices.download');
            Route::get('/befizetesek', [EmployeePaymentController::class, 'index'])
                ->name('payments');

            Route::get('/aszf', [EmployeeLegalPageController::class, 'termsOfService'])
                ->name('legal.terms-of-service');
            Route::get('/etkezesi-es-fizetesi-feltetelek', [EmployeeLegalPageController::class, 'terms'])
                ->name('legal.terms');
            Route::get('/bankkartyas-fizetesi-tajekoztato', [EmployeeLegalPageController::class, 'cardPayment'])
                ->name('legal.card-payment');
            Route::get('/bankkartyas-fizetesi-gyik', [EmployeeLegalPageController::class, 'cardPaymentFaq'])
                ->name('legal.card-payment-faq');
            Route::get('/adatkezeles-online-fizetes', [EmployeeLegalPageController::class, 'dataProcessing'])
                ->name('legal.data-processing');
            Route::get('/fizetesi-folyamat', [EmployeeLegalPageController::class, 'paymentFlow'])
                ->name('legal.payment-flow');
            Route::get('/reklamacio-es-visszaterites', [EmployeeLegalPageController::class, 'complaints'])
                ->name('legal.complaints');
            Route::get('/ugyfelszolgalat', [EmployeeLegalPageController::class, 'customerService'])
                ->name('legal.customer-service');
            Route::get('/impresszum', [EmployeeLegalPageController::class, 'imprint'])
                ->name('legal.imprint');

            Route::get('/fiokom', [EmployeeAccountController::class, 'edit'])
                ->name('account');
            Route::put('/fiokom/szemelyes-adatok', [EmployeeAccountController::class, 'updatePersonal'])
                ->name('account.personal.update');
            Route::put('/fiokom/lakcim', [EmployeeAccountController::class, 'updateAddress'])
                ->name('account.address.update');
            Route::put('/fiokom/jelszo', [EmployeeAccountController::class, 'updatePassword'])
                ->name('account.password.update');
        });
    });
