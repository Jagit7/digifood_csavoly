<?php

use App\Http\Controllers\ParentPortal\Auth\ParentAuthenticatedSessionController;
use App\Http\Controllers\ParentPortal\Auth\ParentAccountActivationController;
use App\Http\Controllers\ParentPortal\ParentAccountController;
use App\Http\Controllers\ParentPortal\ParentChildController;
use App\Http\Controllers\ParentPortal\ParentDashboardController;
use App\Http\Controllers\ParentPortal\ParentHandbookController;
use App\Http\Controllers\ParentPortal\ParentInvoiceController;
use App\Http\Controllers\ParentPortal\ParentLegalPageController;
use App\Http\Controllers\ParentPortal\ParentMealCancellationController;
use App\Http\Controllers\ParentPortal\ParentMenuChoiceController;
use App\Http\Controllers\ParentPortal\ParentMenuController;
use App\Http\Controllers\ParentPortal\ParentMonthlySettlementController;
use App\Http\Controllers\ParentPortal\ParentPaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('szulo')
    ->as('parent.')
    ->group(function () {
        Route::get('/', function () {
            return auth()->check()
                ? redirect()->route('parent.dashboard')
                : redirect()->route('parent.login');
        })->name('home');

        Route::middleware('guest')->group(function () {
            Route::get('/login', [ParentAuthenticatedSessionController::class, 'create'])->name('login');
            Route::post('/login', [ParentAuthenticatedSessionController::class, 'store'])->name('login.store');
        });

        // Az aktiváló link tokennel védett, ezért azt más Digifood szerepkör
        // aktív sessionje mellett is meg kell tudni nyitni és beváltani.
        Route::get('/aktivalas', [ParentAccountActivationController::class, 'create'])->name('activation.create');
        Route::post('/aktivalas', [ParentAccountActivationController::class, 'send'])
            ->middleware('throttle:5,1')
            ->name('activation.send');
        Route::get('/aktivalas/{token}', [ParentAccountActivationController::class, 'show'])->name('activation.show');
        Route::post('/aktivalas/{token}', [ParentAccountActivationController::class, 'store'])->name('activation.store');

        Route::middleware(['auth', 'parent'])->group(function () {
            Route::post('/logout', [ParentAuthenticatedSessionController::class, 'destroy'])->name('logout');
            Route::get('/vezerlopult', ParentDashboardController::class)->name('dashboard');
            Route::get('/gyermekeim', [ParentChildController::class, 'index'])->name('children.index');
            Route::get('/gyermekeim/{child}', [ParentChildController::class, 'show'])->name('children.show');

            Route::get('/etkezesek-es-lemondasok', [ParentMealCancellationController::class, 'index'])
                ->name('meal-cancellations');
            Route::get('/etkezesek-es-lemondasok/nap/{date}', [ParentMealCancellationController::class, 'day'])
                ->name('meal-cancellations.day');
            Route::post('/etkezesek-es-lemondasok/lemondas', [ParentMealCancellationController::class, 'store'])
                ->name('meal-cancellations.store');
            Route::post('/etkezesek-es-lemondasok/visszaallitas', [ParentMealCancellationController::class, 'restore'])
                ->name('meal-cancellations.restore');
            Route::get('/menuvalasztas', [ParentMenuChoiceController::class, 'index'])
                ->name('menu-choices.index');
            Route::put('/menuvalasztas/{child}', [ParentMenuChoiceController::class, 'update'])
                ->name('menu-choices.update');

            // Feltöltött étlapok (intézmény által feltöltött heti/diétás/A-B
            // étlap dokumentumok szülői megtekintése) - a controller
            // (ParentMenuController) és a nézet (parent.menus.index) már
            // korábban elkészült, de a route regisztrációja lemaradt a
            // routes/parent.php-ból, emiatt a sidebar
            // route('parent.menus.index') hívása RouteNotFoundException-t
            // dobott minden szülői oldalon (a sidebar minden szülői nézet
            // része).
            Route::get('/feltoltott-etlapok', [ParentMenuController::class, 'index'])
                ->name('menus.index');
            Route::get('/feltoltott-etlapok/{menu}', [ParentMenuController::class, 'show'])
                ->name('menus.show');
            Route::get('/feltoltott-etlapok/{menu}/letoltes', [ParentMenuController::class, 'download'])
                ->name('menus.download');
            Route::get('/havi-elszamolasok', [ParentMonthlySettlementController::class, 'index'])
                ->name('monthly-settlements.index');
            Route::post('/havi-elszamolasok/fizetes', [ParentMonthlySettlementController::class, 'store'])
                ->name('monthly-settlements.store');
            Route::redirect('/havi-elszamolasok/osszesito', '/havi-elszamolasok')
                ->name('statements');
            Route::get('/befizetesek', [ParentPaymentController::class, 'index'])
                ->name('payments');
            Route::get('/szamlak', [ParentInvoiceController::class, 'index'])
                ->name('invoices');
            Route::get('/szamlak/{invoice}/letoltes', [ParentInvoiceController::class, 'download'])
                ->name('invoices.download');
            Route::get('/aszf', [ParentLegalPageController::class, 'termsOfService'])
                ->name('legal.terms-of-service');
            Route::get('/etkezesi-es-fizetesi-feltetelek', [ParentLegalPageController::class, 'terms'])
                ->name('legal.terms');
            Route::get('/bankkartyas-fizetesi-tajekoztato', [ParentLegalPageController::class, 'cardPayment'])
                ->name('legal.card-payment');
            Route::get('/bankkartyas-fizetesi-gyik', [ParentLegalPageController::class, 'cardPaymentFaq'])
                ->name('legal.card-payment-faq');
            Route::get('/adatkezeles-online-fizetes', [ParentLegalPageController::class, 'dataProcessing'])
                ->name('legal.data-processing');
            Route::get('/fizetesi-folyamat', [ParentLegalPageController::class, 'paymentFlow'])
                ->name('legal.payment-flow');
            Route::get('/reklamacio-es-visszaterites', [ParentLegalPageController::class, 'complaints'])
                ->name('legal.complaints');
            Route::get('/ugyfelszolgalat', [ParentLegalPageController::class, 'customerService'])
                ->name('legal.customer-service');
            Route::get('/impresszum', [ParentLegalPageController::class, 'imprint'])
                ->name('legal.imprint');
            Route::get('/fiokom', [ParentAccountController::class, 'edit'])
                ->name('account');
            Route::put('/fiokom', [ParentAccountController::class, 'updateLegacy'])
                ->name('account.update');
            Route::put('/fiokom/szemelyes-adatok', [ParentAccountController::class, 'updatePersonal'])
                ->name('account.personal.update');
            Route::put('/fiokom/lakcim', [ParentAccountController::class, 'updateAddress'])
                ->name('account.address.update');
            Route::put('/fiokom/szamlazasi-adatok', [ParentAccountController::class, 'updateBilling'])
                ->name('account.billing.update');
            Route::put('/fiokom/jelszo', [ParentAccountController::class, 'updatePassword'])
                ->name('account.password.update');
            Route::get('/kezikonyv', [ParentHandbookController::class, 'index'])
                ->name('handbook');
        });
    });
