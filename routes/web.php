<?php

use App\Http\Controllers\AdminInstitutionAccessController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CardPaymentController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\AbMenuController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\BillingAddressController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\BulkMealCancellationController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\ChildBarcodeController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\ChildController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\ChildMealSettingController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\ChildPrintListController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\ClassCancellationController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\ClassGroupController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\DailyAttendanceEmailSettingController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\DailyHeadcountEmailSettingController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\DailyOperationController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\DataImportController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\DietaryMenuController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\EmailCampaignController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\EmployeeMealCancellationController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\Finance\InstitutionDebtController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\Finance\InstitutionFinanceExportController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\Finance\InstitutionFinanceHelpController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\Finance\InstitutionInvoiceController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\Finance\CibTransactionController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\Finance\InstitutionPaymentController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\HandbookController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\InstitutionContactController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\InstitutionContextController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\InstitutionDashboardController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\InstitutionEmployeeBarcodeController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\InstitutionEmployeeController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\InstitutionEmployeeMealSettingController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\InstitutionInvoicingSettingController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\InstitutionMealPackageController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\InstitutionMealParticipantController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\InstitutionMealPriceController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\InstitutionMealTypeController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\InstitutionProfileController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\InstitutionSettingController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\MealCancellationController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\MealKioskAccessController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\MenuChoiceController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\MenuController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\EmployeeActivationInviteController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\ParentActivationInviteController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\ParentController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\PaymentObligation\EmployeePaymentObligationController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\PaymentObligation\FinancialAdjustmentController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\PaymentObligation\PaymentObligationController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\ReferenceDataController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\ReportController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\SchoolBreakController;
use App\Http\Controllers\Dashboard\InstitutionAdmin\WorkingDayController;
use App\Http\Controllers\Dashboard\SuperAdmin\AuditLogController;
use App\Http\Controllers\Dashboard\SuperAdmin\BillingPartnerController;
use App\Http\Controllers\Dashboard\SuperAdmin\ChildController as SuperAdminChildController;
use App\Http\Controllers\Dashboard\SuperAdmin\InstitutionAdminDeviceController;
use App\Http\Controllers\Dashboard\SuperAdmin\InstitutionBillingRateController;
use App\Http\Controllers\Dashboard\SuperAdmin\ParentController as SuperAdminParentController;
use App\Http\Controllers\Dashboard\SuperAdmin\PartnerMonthlyBillingController;
use App\Http\Controllers\Dashboard\SuperAdmin\ReportsController;
use App\Http\Controllers\Dashboard\SuperAdmin\RevenueOverviewController;
use App\Http\Controllers\Dashboard\SuperAdmin\RoleController as SuperAdminRoleController;
use App\Http\Controllers\Dashboard\SuperAdmin\SaasBillingSummaryController;
use App\Http\Controllers\Dashboard\SuperAdmin\SuperAdminOverviewController;
use App\Http\Controllers\InstitutionController;
use App\Http\Controllers\Kiosk\MealKioskController;
use App\Http\Controllers\ProfileController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect(route('auth.login', [], false));
    }

    return redirect(route('login.redirect', [], false));
})->name('home');

Route::prefix('payments/card')->name('payments.card.')->group(function () {
    // A bankok szerver-szerver visszahívása (callback) mindig POST -
    // korábban Route::any() GET-et is engedett, ami feleslegesen tágra
    // nyitotta a támadási felületet (pl. egy link megnyitásával is ki
    // lehetett volna váltani a végpontot). A tényleges védelmet a kérés
    // aláírás-ellenőrzése adja (ld. CibCardPaymentGateway), ez a
    // szűkítés csak defense-in-depth.
    Route::post('/{provider}/callback', [CardPaymentController::class, 'callback'])->name('callback');
    Route::get('/return', [CardPaymentController::class, 'returnFromGateway'])->name('return');
});

Route::prefix('auth')->name('auth.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', function () {
            return view('auth.login');
        })->name('login');

        Route::post('/login', [AuthController::class, 'login'])->name('login_form');

        Route::get('/register', function () {
            return view('auth.register');
        })->name('register');

        Route::post('/register', [AuthController::class, 'register'])->name('register_form');

        Route::get('/forgot', function () {
            return view('auth.forgot');
        })->name('forgot');

        Route::post('/forgot', [AuthController::class, 'sendResetLinkEmail'])->name('forgot_send');
    });

    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware('auth')
        ->name('logout');
});

Route::get('/belepes', function () {
    if (! auth()->check()) {
        return redirect(route('auth.login', [], false));
    }

    $user = auth()->user();

    return match ($user->role) {
        User::ROLE_SUPER_ADMIN => redirect(route('dashboard.superadmin', [], false)),
        User::ROLE_INSTITUTION_ADMIN, User::ROLE_INSTITUTION_SECRETARY, User::ROLE_KITCHEN, User::ROLE_MUNICIPALITY => redirect(route('dashboard.institution.home', [], false)),
        User::ROLE_MEAL_KIOSK => redirect(route('kiosk.show', [], false)),
        User::ROLE_PARENT => redirect(route('parent.dashboard', [], false)),
        default => redirect(route('home', [], false)),
    };
})->name('login.redirect');

Route::get('/adatkezelesi-tajekoztato', function () {
    return view('legal.privacy-policy');
})->name('legal.privacy');

Route::get('/institution-invite/{token}', [AdminInstitutionAccessController::class, 'acceptInvite'])->name('institution-invite.accept');
Route::post('/institution-invite/{token}', [AdminInstitutionAccessController::class, 'completeInvite'])->name('institution-invite.complete');

Route::middleware('auth')->prefix('dashboard')->name('dashboard.')->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    /*
    |--------------------------------------------------------------------------
    | Superadmin
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/superadmin', [SuperAdminOverviewController::class, 'index'])->name('superadmin');
        Route::get('/superadmin/roles', [SuperAdminRoleController::class, 'index'])->name('superadmin.roles.index');
        Route::prefix('superadmin/institution-admin-devices')->name('superadmin.institution-admin-devices.')->group(function () {
            Route::get('/', [InstitutionAdminDeviceController::class, 'index'])->name('index');
            Route::post('/{institutionAdminDevice}/approve', [InstitutionAdminDeviceController::class, 'approve'])->name('approve');
            Route::post('/{institutionAdminDevice}/reject', [InstitutionAdminDeviceController::class, 'reject'])->name('reject');
            Route::post('/{institutionAdminDevice}/revoke', [InstitutionAdminDeviceController::class, 'revoke'])->name('revoke');
        });
        Route::get('/superadmin/audit-logs', [AuditLogController::class, 'index'])->name('superadmin.audit-logs.index');
        Route::get('/superadmin/reports', [ReportsController::class, 'index'])->name('superadmin.reports.index');
        Route::prefix('superadmin/children')->name('superadmin.children.')->group(function () {
            Route::get('/', [SuperAdminChildController::class, 'index'])->name('index');
            Route::get('/{child}', [SuperAdminChildController::class, 'show'])->name('show');
        });
        Route::prefix('superadmin/parents')->name('superadmin.parents.')->group(function () {
            Route::get('/', [SuperAdminParentController::class, 'index'])->name('index');
            Route::get('/{parent}', [SuperAdminParentController::class, 'show'])->name('show');
        });
        Route::prefix('superadmin/billing-partners')->name('billing-partners.')->group(function () {
            Route::get('/', [BillingPartnerController::class, 'index'])->name('index');
            Route::get('/create', [BillingPartnerController::class, 'create'])->name('create');
            Route::post('/', [BillingPartnerController::class, 'store'])->name('store');
            Route::get('/{billingPartner}/edit', [BillingPartnerController::class, 'edit'])->name('edit');
            Route::put('/{billingPartner}', [BillingPartnerController::class, 'update'])->name('update');
        });
        Route::prefix('superadmin/partner-monthly-billings')->name('partner-monthly-billings.')->group(function () {
            Route::get('/', [PartnerMonthlyBillingController::class, 'index'])->name('index');
            Route::get('/{billingPartner}', [PartnerMonthlyBillingController::class, 'show'])->name('show');
            Route::post('/{billingPartner}', [PartnerMonthlyBillingController::class, 'store'])->name('store');
            Route::post('/snapshots/{monthlyBilling}/recalculate', [PartnerMonthlyBillingController::class, 'recalculate'])->name('recalculate');
            Route::post('/snapshots/{monthlyBilling}/mark-invoiced', [PartnerMonthlyBillingController::class, 'markAsInvoiced'])->name('mark-invoiced');
            Route::post('/snapshots/{monthlyBilling}/mark-paid', [PartnerMonthlyBillingController::class, 'markAsPaid'])->name('mark-paid');
        });
        Route::prefix('superadmin/saas-billing-summary')->name('saas-billing-summary.')->group(function () {
            Route::get('/', [SaasBillingSummaryController::class, 'index'])->name('index');
            Route::post('/send', [SaasBillingSummaryController::class, 'send'])->name('send');
            Route::post('/items/{item}/mark-invoiced', [SaasBillingSummaryController::class, 'markItemInvoiced'])->name('items.mark-invoiced');
            Route::post('/items/{item}/mark-paid', [SaasBillingSummaryController::class, 'markItemPaid'])->name('items.mark-paid');
        });
        Route::get('superadmin/revenue-overview', [RevenueOverviewController::class, 'index'])->name('revenue-overview.index');
        Route::get('institutions/trashed', [InstitutionController::class, 'trashed'])
            ->name('institutions.trashed');

        Route::patch('institutions/{institution}/toggle-active', [InstitutionController::class, 'toggleActive'])
            ->name('institutions.toggle-active');

        Route::patch('institutions/{institution}/restore', [InstitutionController::class, 'restore'])
            ->name('institutions.restore');

        Route::prefix('institutions/{institution}/billing-rates')->name('institutions.billing-rates.')->group(function () {
            Route::get('/', [InstitutionBillingRateController::class, 'index'])->name('index');
            Route::get('/create', [InstitutionBillingRateController::class, 'create'])->name('create');
            Route::post('/', [InstitutionBillingRateController::class, 'store'])->name('store');
            Route::get('/{billingRate}/edit', [InstitutionBillingRateController::class, 'edit'])->name('edit');
            Route::put('/{billingRate}', [InstitutionBillingRateController::class, 'update'])->name('update');
        });

        Route::resource('institutions', InstitutionController::class)->except(['show']);

        Route::prefix('admin-access')->name('admin-access.')->group(function () {
            Route::get('/', [AdminInstitutionAccessController::class, 'index'])->name('index');
            Route::get('/invite', [AdminInstitutionAccessController::class, 'createInvite'])->name('invite.create');
            Route::post('/invite', [AdminInstitutionAccessController::class, 'storeInvite'])->name('invite.store');
            Route::post('/invitations/{invitation}/resend', [AdminInstitutionAccessController::class, 'resendInvite'])->name('invite.resend');
            Route::get('/{user}/edit', [AdminInstitutionAccessController::class, 'edit'])->name('edit');
            Route::put('/{user}', [AdminInstitutionAccessController::class, 'update'])->name('update');
            Route::patch('/{user}/toggle-active', [AdminInstitutionAccessController::class, 'toggleActive'])->name('toggle-active');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Intézményi dashboard
    |--------------------------------------------------------------------------
    */
    Route::middleware(['institution-context', 'role:institution_admin,institution_secretary,kitchen,municipality'])->group(function () {

        Route::get('/institution-admin', InstitutionDashboardController::class)->name('institution.home');
        Route::post('/institution-admin/context', InstitutionContextController::class)->name('institution.context.update');

        /*
        |--------------------------------------------------------------------------
        | Napi működés
        |--------------------------------------------------------------------------
        */
        Route::prefix('institution-admin/daily')->name('institution.daily.')->group(function () {
            Route::get('/today-counts', [DailyOperationController::class, 'todayCounts'])->name('today-counts');
            Route::get('/today-counts/print', [DailyOperationController::class, 'printTodayCounts'])
                ->name('today-counts.print');
            Route::get('/today-counts/attendance-sheet', [DailyOperationController::class, 'printAttendanceSheet'])
                ->name('today-counts.attendance-sheet');
            Route::get('/cancellations', [DailyOperationController::class, 'cancellations'])->name('cancellations');
            Route::get('/dietary-children', [DailyOperationController::class, 'dietaryChildren'])->name('dietary-children');
            Route::get('/dietary-children/print', [DailyOperationController::class, 'printDietaryChildren'])
                ->name('dietary-children.print');
            Route::get('/dietary-children/export', [DailyOperationController::class, 'exportDietaryChildren'])
                ->name('dietary-children.export');
        });

        /*
        |--------------------------------------------------------------------------
        | Étlapok és menük
        |--------------------------------------------------------------------------
        */
        Route::middleware('role:institution_admin,kitchen')
            ->prefix('institution-admin/menus')
            ->name('institution.menus.')
            ->group(function () {

                Route::get('/', [MenuController::class, 'index'])->name('index');
                Route::get('/create', [MenuController::class, 'create'])->name('create');
                Route::post('/', [MenuController::class, 'store'])->name('store');

                Route::get('/dietary', [DietaryMenuController::class, 'index'])->name('dietary.index');
                Route::get('/dietary/create', [DietaryMenuController::class, 'create'])->name('dietary.create');
                Route::post('/dietary', [DietaryMenuController::class, 'store'])->name('dietary.store');

                Route::prefix('ab')->name('ab.')->group(function () {
                    Route::get('/', [AbMenuController::class, 'index'])->name('index');
                    Route::get('/import', [AbMenuController::class, 'create'])->name('create');
                    Route::post('/import', [AbMenuController::class, 'store'])->name('store');
                    Route::get('/sample', [AbMenuController::class, 'sample'])->name('sample');
                    Route::get('/export', [AbMenuController::class, 'export'])->name('export');
                    Route::put('/items/{abMenuItem}', [AbMenuController::class, 'updateItem'])->name('items.update');
                    Route::delete('/items/{abMenuItem}', [AbMenuController::class, 'destroyItem'])->name('items.destroy');
                    Route::get('/rows/{abMenuItem}/edit', [AbMenuController::class, 'rowEdit'])->name('rows.edit');
                    Route::put('/rows/{abMenuItem}', [AbMenuController::class, 'rowUpdate'])->name('rows.update');
                    Route::delete('/rows/{abMenuItem}', [AbMenuController::class, 'rowDestroy'])->name('rows.destroy');
                    Route::get('/{abMenuPlan}', [AbMenuController::class, 'show'])->name('show');
                    Route::get('/{abMenuPlan}/edit', [AbMenuController::class, 'edit'])->name('edit');
                    Route::put('/{abMenuPlan}', [AbMenuController::class, 'update'])->name('update');
                    Route::delete('/{abMenuPlan}', [AbMenuController::class, 'destroy'])->name('destroy');
                });

                Route::get('/choices', [MenuChoiceController::class, 'index'])->name('choices.index');
                Route::post('/choices/{abMenuPlan}/open', [MenuChoiceController::class, 'open'])->name('choices.open');
                Route::post('/choices/close', [MenuChoiceController::class, 'close'])->name('choices.close');
                Route::post('/choices/reopen', [MenuChoiceController::class, 'reopen'])->name('choices.reopen');
                Route::get('/choices/export', [MenuChoiceController::class, 'export'])->name('choices.export');

                Route::get('/{menu}/download', [MenuController::class, 'download'])->name('download');
                Route::get('/{menu}', [MenuController::class, 'show'])->name('show');
                Route::get('/{menu}/edit', [MenuController::class, 'edit'])->name('edit');
                Route::put('/{menu}', [MenuController::class, 'update'])->name('update');
                Route::delete('/{menu}', [MenuController::class, 'destroy'])->name('destroy');
            });

        /*
        |--------------------------------------------------------------------------
        | Iskolai napok és szünetek
        |--------------------------------------------------------------------------
        */

        Route::middleware('role:institution_admin')
            ->prefix('institution-admin/school-breaks')
            ->name('institution.school-breaks.')
            ->group(function () {

                // Iskolai szünetek
                Route::get('/calendar', [SchoolBreakController::class, 'calendar'])->name('calendar');
                Route::get('/calendar/{date}/cancelled', [SchoolBreakController::class, 'calendarCancelled'])
                    ->where('date', '\\d{4}-\\d{2}-\\d{2}')
                    ->name('calendar.cancelled');
                Route::get('/calendar/{date}', [SchoolBreakController::class, 'calendarDay'])
                    ->where('date', '\\d{4}-\\d{2}-\\d{2}')
                    ->name('calendar.day');
                Route::get('/', [SchoolBreakController::class, 'index'])->name('index');
                Route::get('/create', [SchoolBreakController::class, 'create'])->name('create');
                Route::post('/', [SchoolBreakController::class, 'store'])->name('store');
                Route::get('/{schoolBreak}/edit', [SchoolBreakController::class, 'edit'])->name('edit');
                Route::put('/{schoolBreak}', [SchoolBreakController::class, 'update'])->name('update');
                Route::delete('/{schoolBreak}', [SchoolBreakController::class, 'destroy'])->name('destroy');

                // Tanítási / óvodai szombatok
                Route::prefix('working-days')->name('working-days.')->group(function () {
                    Route::get('/', [WorkingDayController::class, 'index'])->name('index');
                    Route::get('/create', [WorkingDayController::class, 'create'])->name('create');
                    Route::post('/', [WorkingDayController::class, 'store'])->name('store');
                    Route::get('/{workingDay}/edit', [WorkingDayController::class, 'edit'])->name('edit');
                    Route::put('/{workingDay}', [WorkingDayController::class, 'update'])->name('update');
                    Route::delete('/{workingDay}', [WorkingDayController::class, 'destroy'])->name('destroy');
                });
            });

        /*
        |--------------------------------------------------------------------------
        | Osztály / csoport lemondások
        |--------------------------------------------------------------------------
        */

        Route::middleware('role:institution_admin')
            ->prefix('institution-admin/class-cancellations')
            ->name('institution.class-cancellations.')
            ->group(function () {

                Route::get('/', [ClassCancellationController::class, 'index'])->name('index');
                Route::get('/create', [ClassCancellationController::class, 'create'])->name('create');
                Route::post('/', [ClassCancellationController::class, 'store'])->name('store');

                Route::get('/{classCancellation}/edit', [ClassCancellationController::class, 'edit'])->name('edit');
                Route::put('/{classCancellation}', [ClassCancellationController::class, 'update'])->name('update');

                Route::delete('/{classCancellation}', [ClassCancellationController::class, 'destroy'])->name('destroy');
            });

        Route::middleware('role:institution_admin')
            ->prefix('institution-admin/meal-cancellations')
            ->name('institution.meal-cancellations.')
            ->group(function () {
                Route::get('/', [MealCancellationController::class, 'index'])->name('index');
                Route::get('/create', [MealCancellationController::class, 'create'])->name('create');
                Route::post('/', [MealCancellationController::class, 'store'])->name('store');
                Route::get('/bulk/create', [BulkMealCancellationController::class, 'create'])->name('bulk.create');
                Route::post('/bulk/preview', [BulkMealCancellationController::class, 'preview'])->name('bulk.preview');
                Route::post('/bulk/store', [BulkMealCancellationController::class, 'store'])->name('bulk.store');
                Route::get('/bulk/operations', [BulkMealCancellationController::class, 'operations'])->name('bulk.index');
                Route::get('/bulk/operations/{batch}', [BulkMealCancellationController::class, 'show'])->name('bulk.show');
                Route::delete('/recurring/{recurringCancellationRule}', [MealCancellationController::class, 'destroyRecurring'])
                    ->name('recurring.destroy');
                Route::delete('/{mealCancellation}', [MealCancellationController::class, 'destroy'])
                    ->name('destroy');
            });
        /*
        |--------------------------------------------------------------------------
        | Gyerekek, szülők, csoportok
        |--------------------------------------------------------------------------
        */
        Route::middleware('role:institution_admin,institution_secretary')
            ->prefix('institution-admin')
            ->name('institution.')
            ->group(function () {
                Route::prefix('imports')->name('imports.')->group(function () {
                    Route::get('/', [DataImportController::class, 'index'])->name('index');
                    Route::get('/school-standard', [DataImportController::class, 'createSchoolStandard'])
                        ->name('school-standard.create');
                    Route::post('/school-standard', [DataImportController::class, 'storeSchoolStandard'])
                        ->name('school-standard.store');
                    Route::get('/kindergarten', [DataImportController::class, 'createKindergarten'])
                        ->name('kindergarten.create');
                    Route::post('/kindergarten', [DataImportController::class, 'storeKindergarten'])
                        ->name('kindergarten.store');
                    Route::get('/{import}', [DataImportController::class, 'show'])->name('show');
                });

                Route::resource('children', ChildController::class)
                    ->only(['index', 'create', 'store', 'edit', 'update']);
                Route::get('children-export', [ChildController::class, 'export'])
                    ->name('children.export');
                Route::post('children/{child}/verify', [ChildController::class, 'markVerified'])
                    ->name('children.verify');
                Route::delete('children/{child}/verify', [ChildController::class, 'unmarkVerified'])
                    ->name('children.unverify');
                Route::post('children/{child}/discount', [ChildController::class, 'updateDiscount'])
                    ->name('children.discount.update');
                Route::middleware('role:institution_admin')->group(function () {
                    Route::resource('employees', InstitutionEmployeeController::class)
                        ->only(['index', 'create', 'store', 'edit', 'update']);
                    Route::post('employees/{employee}/toggle-active', [InstitutionEmployeeController::class, 'toggleActive'])
                        ->name('employees.toggle-active');
                    Route::post('employees/{employee}/barcode', [InstitutionEmployeeBarcodeController::class, 'store'])
                        ->name('employees.barcode.store');
                    Route::delete('employees/{employee}/barcode', [InstitutionEmployeeBarcodeController::class, 'destroy'])
                        ->name('employees.barcode.destroy');
                    Route::post('employees/{employee}/barcode/activate', [InstitutionEmployeeBarcodeController::class, 'activate'])
                        ->name('employees.barcode.activate');
                    Route::post('employees/{employee}/barcode/regenerate', [InstitutionEmployeeBarcodeController::class, 'regenerate'])
                        ->name('employees.barcode.regenerate');
                    Route::get('employees/{employee}/barcode/print', [InstitutionEmployeeBarcodeController::class, 'print'])
                        ->name('employees.barcode.print');
                    Route::prefix('employees/{employee}/meal-settings')->name('employees.meal-settings.')->group(function () {
                        Route::get('/', [InstitutionEmployeeMealSettingController::class, 'index'])->name('index');
                        Route::get('/create', [InstitutionEmployeeMealSettingController::class, 'create'])->name('create');
                        Route::post('/', [InstitutionEmployeeMealSettingController::class, 'store'])->name('store');
                        Route::get('/{mealSetting}', [InstitutionEmployeeMealSettingController::class, 'show'])->name('show');
                    });
                    Route::prefix('employees/meal-cancellations')->name('employees.meal-cancellations.')->group(function () {
                        Route::get('/', [EmployeeMealCancellationController::class, 'index'])->name('index');
                        Route::post('/', [EmployeeMealCancellationController::class, 'store'])->name('store');
                        Route::post('/{employeeMealCancellation}/restore', [EmployeeMealCancellationController::class, 'restore'])->name('restore');
                        Route::delete('/recurring/{employeeRecurringRule}', [EmployeeMealCancellationController::class, 'destroyRecurring'])
                            ->name('recurring.destroy');
                    });
                });
                Route::prefix('children/barcodes')->name('children.barcodes.')->group(function () {
                    Route::get('/', [ChildBarcodeController::class, 'index'])->name('index');
                    Route::post('/generate-selected', [ChildBarcodeController::class, 'bulkGenerate'])->name('bulk-generate');
                    Route::post('/generate-missing', [ChildBarcodeController::class, 'generateMissing'])->name('generate-missing');
                    Route::post('/print-selected', [ChildBarcodeController::class, 'printSelected'])->name('print-selected');
                    Route::get('/print-batches/{token}', [ChildBarcodeController::class, 'printBatch'])->name('print-batch');
                    Route::get('/print-active', [ChildBarcodeController::class, 'printAllActive'])->name('print-active');
                    Route::get('/kiosk', [MealKioskAccessController::class, 'edit'])->name('kiosk.edit');
                    Route::put('/kiosk', [MealKioskAccessController::class, 'update'])->name('kiosk.update');
                    Route::post('/kiosk/control-card', [MealKioskAccessController::class, 'generateControlCard'])->name('kiosk.control-card.generate');
                    Route::post('/kiosk/control-card/regenerate', [MealKioskAccessController::class, 'regenerateControlCard'])->name('kiosk.control-card.regenerate');
                    Route::get('/kiosk/control-card/print', [MealKioskAccessController::class, 'printControlCard'])->name('kiosk.control-card.print');
                });
                Route::post('children/{child}/barcode', [ChildBarcodeController::class, 'store'])
                    ->name('children.barcode.store');
                Route::delete('children/{child}/barcode', [ChildBarcodeController::class, 'destroy'])
                    ->name('children.barcode.destroy');
                Route::post('children/{child}/barcode/regenerate', [ChildBarcodeController::class, 'regenerate'])
                    ->name('children.barcode.regenerate');
                Route::get('children/{child}/barcode/print', [ChildBarcodeController::class, 'printChild'])
                    ->name('children.barcode.print');
                Route::prefix('children/print-list')->name('children.print-list.')->group(function () {
                    Route::get('/', [ChildPrintListController::class, 'index'])->name('index');
                    Route::get('/generate', [ChildPrintListController::class, 'generate'])->name('generate');
                });
                Route::prefix('children/meal-participants')->name('children.meal-participants.')->group(function () {
                    Route::get('/', [InstitutionMealParticipantController::class, 'index'])->name('index');
                    Route::post('/bulk-enable', [InstitutionMealParticipantController::class, 'bulkEnable'])->name('bulk-enable');
                    Route::post('/bulk-disable', [InstitutionMealParticipantController::class, 'bulkDisable'])->name('bulk-disable');
                });
                Route::post('children/{child}/meal-participation/enable', [InstitutionMealParticipantController::class, 'enable'])
                    ->name('children.meal-participants.enable');
                Route::post('children/{child}/meal-participation/disable', [InstitutionMealParticipantController::class, 'disable'])
                    ->name('children.meal-participants.disable');
                Route::prefix('children/{child}/meal-settings')->name('children.meal-settings.')->group(function () {
                    Route::get('/', [ChildMealSettingController::class, 'index'])->name('index');
                    Route::get('/create', [ChildMealSettingController::class, 'create'])->name('create');
                    Route::post('/', [ChildMealSettingController::class, 'store'])->name('store');
                    Route::post('/{mealSetting}/close', [ChildMealSettingController::class, 'close'])->name('close');
                    Route::post('/{mealSetting}/reopen', [ChildMealSettingController::class, 'reopen'])->name('reopen');
                    Route::get('/{mealSetting}/edit', [ChildMealSettingController::class, 'edit'])->name('edit');
                    Route::put('/{mealSetting}', [ChildMealSettingController::class, 'update'])->name('update');
                    Route::get('/{mealSetting}', [ChildMealSettingController::class, 'show'])->name('show');
                });
                Route::resource('parents', ParentController::class)
                    ->only(['index', 'edit', 'update']);
                Route::middleware('role:institution_admin')->prefix('billing-addresses')->name('billing-addresses.')->group(function () {
                    Route::get('/', [BillingAddressController::class, 'index'])->name('index');
                    Route::post('/', [BillingAddressController::class, 'store'])->name('store');
                    Route::put('/{billingProfile}', [BillingAddressController::class, 'update'])->name('update');
                    Route::put('/{child}/basics', [BillingAddressController::class, 'updateBasics'])->name('children.basics.update');
                    Route::put('/{child}/guardian-emails', [BillingAddressController::class, 'updateGuardianEmails'])->name('children.guardian-emails.update');
                    Route::put('/{child}/payer', [BillingAddressController::class, 'setPrimaryGuardian'])->name('children.payer.update');
                    Route::put('/guardians/{guardian}/bank-account', [BillingAddressController::class, 'updateGuardianBankAccount'])
                        ->name('guardian.bank-account.update');
                });
                Route::middleware('role:institution_admin')->prefix('communication/emails')->name('communication.emails.')->group(function () {
                    Route::get('/', [EmailCampaignController::class, 'index'])->name('index');
                    Route::get('/create', [EmailCampaignController::class, 'create'])->name('create');
                    Route::post('/', [EmailCampaignController::class, 'store'])->name('store');
                    Route::get('/{emailCampaign}', [EmailCampaignController::class, 'show'])->name('show');
                });
                Route::middleware('role:institution_admin')->prefix('communication/parent-activation-invite')->name('communication.parent-activation-invite.')->group(function () {
                    Route::get('/', [ParentActivationInviteController::class, 'index'])->name('index');
                    Route::post('/', [ParentActivationInviteController::class, 'store'])->name('store');
                    Route::post('/{guardian}/send-individual', [ParentActivationInviteController::class, 'sendIndividual'])->name('send-individual');
                    Route::post('/{guardian}/generate-link', [ParentActivationInviteController::class, 'generateLink'])->name('generate-link');
                });
                Route::middleware('role:institution_admin')->prefix('communication/employee-activation-invite')->name('communication.employee-activation-invite.')->group(function () {
                    Route::get('/', [EmployeeActivationInviteController::class, 'index'])->name('index');
                    Route::post('/', [EmployeeActivationInviteController::class, 'store'])->name('store');
                    Route::post('/{employee}/send-individual', [EmployeeActivationInviteController::class, 'sendIndividual'])->name('send-individual');
                    Route::post('/{employee}/generate-link', [EmployeeActivationInviteController::class, 'generateLink'])->name('generate-link');
                });
                Route::get('class-groups/promotion', [ClassGroupController::class, 'promotion'])
                    ->name('class-groups.promotion');
                Route::post('class-groups/promotion', [ClassGroupController::class, 'promote'])
                    ->name('class-groups.promote');
                Route::post('class-groups/{classGroup}/move-children', [ClassGroupController::class, 'moveChildren'])
                    ->name('class-groups.move-children');
                Route::resource('class-groups', ClassGroupController::class)
                    ->only(['index', 'show']);
            });

        /*
        |--------------------------------------------------------------------------
        | Intézmény
        |--------------------------------------------------------------------------
        */
        Route::middleware('role:institution_admin')
            ->prefix('institution-admin/institution')
            ->name('institution.')
            ->group(function () {
                Route::get('/profile', [InstitutionProfileController::class, 'edit'])->name('profile.edit');
                Route::put('/profile', [InstitutionProfileController::class, 'update'])->name('profile.update');

                Route::get('/settings', [InstitutionSettingController::class, 'edit'])->name('settings.edit');
                Route::put('/settings', [InstitutionSettingController::class, 'update'])->name('settings.update');
                Route::get('/settings/invoicing', [InstitutionInvoicingSettingController::class, 'edit'])->name('settings.invoicing.edit');
                Route::put('/settings/invoicing', [InstitutionInvoicingSettingController::class, 'update'])->name('settings.invoicing.update');
                Route::get('/settings/invoicing/billingo-document-blocks', [InstitutionInvoicingSettingController::class, 'billingoDocumentBlocks'])->name('settings.invoicing.billingo-document-blocks');

                /*
                |----------------------------------------------------------------
                | Napi jelenléti ív e-mailben (kizárólag óvodai intézménynél)
                |----------------------------------------------------------------
                */
                Route::prefix('settings/daily-attendance')->name('settings.daily-attendance.')->group(function () {
                    Route::post('/', [DailyAttendanceEmailSettingController::class, 'updateSchedule'])->name('update');
                    Route::post('/groups/{groupName}/recipients', [DailyAttendanceEmailSettingController::class, 'updateRecipients'])->name('recipients.update');
                    Route::get('/groups/{groupName}/preview', [DailyAttendanceEmailSettingController::class, 'preview'])->name('preview');
                    Route::post('/groups/{groupName}/test-send', [DailyAttendanceEmailSettingController::class, 'testSend'])->name('test-send');
                });

                /*
                |----------------------------------------------------------------
                | Napi létszám e-mailek (osztályonként/csoportonként, iskolai
                | és óvodai intézménynél egyaránt)
                |----------------------------------------------------------------
                */
                Route::prefix('daily-headcount-emails')->name('daily-headcount-emails.')->group(function () {
                    Route::get('/', [DailyHeadcountEmailSettingController::class, 'index'])->name('index');
                    Route::post('/schedule', [DailyHeadcountEmailSettingController::class, 'updateSchedule'])->name('schedule.update');
                    Route::post('/groups/{groupName}', [DailyHeadcountEmailSettingController::class, 'updateGroup'])->name('groups.update');
                    Route::get('/groups/{groupName}/preview', [DailyHeadcountEmailSettingController::class, 'preview'])->name('groups.preview');
                    Route::post('/groups/{groupName}/test-send', [DailyHeadcountEmailSettingController::class, 'testSend'])->name('groups.test-send');
                });

                Route::prefix('meal-types')->name('meal-types.')->group(function () {
                    Route::get('/', [InstitutionMealTypeController::class, 'index'])->name('index');
                    Route::get('/{institutionMealType}/edit', [InstitutionMealTypeController::class, 'edit'])->name('edit');
                    Route::put('/{institutionMealType}', [InstitutionMealTypeController::class, 'update'])->name('update');

                    Route::prefix('{institutionMealType}/prices')->name('prices.')->group(function () {
                        Route::get('/create', [InstitutionMealPriceController::class, 'create'])->name('create');
                        Route::post('/', [InstitutionMealPriceController::class, 'store'])->name('store');
                        Route::get('/history', [InstitutionMealPriceController::class, 'history'])->name('history');
                        Route::get('/{price}/edit', [InstitutionMealPriceController::class, 'edit'])->name('edit');
                        Route::put('/{price}', [InstitutionMealPriceController::class, 'update'])->name('update');
                    });
                });

                Route::prefix('meal-packages')->name('meal-packages.')->group(function () {
                    Route::get('/', [InstitutionMealPackageController::class, 'index'])->name('index');
                    Route::get('/create', [InstitutionMealPackageController::class, 'create'])->name('create');
                    Route::post('/', [InstitutionMealPackageController::class, 'store'])->name('store');
                    Route::get('/{mealPackage}', [InstitutionMealPackageController::class, 'show'])->name('show');
                    Route::get('/{mealPackage}/edit', [InstitutionMealPackageController::class, 'edit'])->name('edit');
                    Route::put('/{mealPackage}', [InstitutionMealPackageController::class, 'update'])->name('update');
                    Route::delete('/{mealPackage}', [InstitutionMealPackageController::class, 'destroy'])->name('destroy');
                });

                Route::prefix('reference-data')->name('reference-data.')->group(function () {
                    Route::get('/', [ReferenceDataController::class, 'index'])->name('index');
                    Route::put('/cancellation-deadline', [ReferenceDataController::class, 'updateCancellation'])
                        ->name('cancellation.update');
                    Route::post('/discounts', [ReferenceDataController::class, 'storeDiscount'])
                        ->name('discounts.store');
                    Route::put('/discounts/{discountType}', [ReferenceDataController::class, 'updateDiscount'])
                        ->name('discounts.update');
                    Route::delete('/discounts/{discountType}', [ReferenceDataController::class, 'destroyDiscount'])
                        ->name('discounts.destroy');
                    Route::post('/dietary-restrictions', [ReferenceDataController::class, 'storeRestriction'])
                        ->name('restrictions.store');
                    Route::put('/dietary-restrictions/{dietaryRestriction}', [ReferenceDataController::class, 'updateRestriction'])
                        ->name('restrictions.update');
                    Route::delete('/dietary-restrictions/{dietaryRestriction}', [ReferenceDataController::class, 'destroyRestriction'])
                        ->name('restrictions.destroy');
                });

                Route::resource('contacts', InstitutionContactController::class)->except(['show']);
            });

        /*
        |--------------------------------------------------------------------------
        | Pénzügyek
        |--------------------------------------------------------------------------
        */
        // 2. FÁZIS - "7. SZÁMLA SZTORNÓ / JOGOSULTSÁG": a "institution_secretary"
        // szerepkör itt a csoport-szintű kapun kizárólag azért kapott helyet,
        // hogy a lentebbi 'invoices'/'invoices.show'/'invoices.cancel' route-ok
        // saját, szűkebb middleware-je egyáltalán átengedhesse őket (a
        // middleware-ek AND logikával összeadódnak, nem írják felül egymást) -
        // minden MÁS pénzügyi route (payments/debts/exports/stb.) továbbra is
        // kizárólag 'institution_admin'-ra van szűkítve a saját route-szintű
        // middleware-jében, tehát ez a bővítés önmagában NEM ad institution_secretary-nak
        // hozzáférést semmi máshoz ezen a csoporton belül.
        Route::middleware('role:institution_admin,municipality,institution_secretary')
            ->prefix('institution-admin/finance')
            ->name('institution.finance.')
            ->group(function () {
                Route::get('/help', [InstitutionFinanceHelpController::class, 'index'])
                    ->middleware('role:institution_admin')
                    ->name('help');
                Route::get('/payments', [InstitutionPaymentController::class, 'index'])
                    ->middleware('role:institution_admin')
                    ->name('payments');
                Route::get('/payments/cib-transactions', [CibTransactionController::class, 'index'])
                    ->middleware('role:institution_admin')
                    ->name('cib-transactions');
                Route::get('/payments/children/search', [InstitutionPaymentController::class, 'searchChildren'])
                    ->middleware('role:institution_admin')
                    ->name('payments.children.search');
                Route::get('/payments/children/{child}/guardians', [InstitutionPaymentController::class, 'childGuardians'])
                    ->middleware('role:institution_admin')
                    ->name('payments.children.guardians');
                Route::get('/payments/children/{child}/statements', [InstitutionPaymentController::class, 'childStatements'])
                    ->middleware('role:institution_admin')
                    ->name('payments.children.statements');
                Route::get('/payments/create', [InstitutionPaymentController::class, 'create'])
                    ->middleware('role:institution_admin')
                    ->name('payments.create');
                Route::post('/payments', [InstitutionPaymentController::class, 'store'])
                    ->middleware('role:institution_admin')
                    ->name('payments.store');
                Route::get('/payments/{payment}', [InstitutionPaymentController::class, 'show'])
                    ->middleware('role:institution_admin')
                    ->name('payments.show');
                Route::get('/payments/{payment}/edit', [InstitutionPaymentController::class, 'edit'])
                    ->middleware('role:institution_admin')
                    ->name('payments.edit');
                Route::put('/payments/{payment}', [InstitutionPaymentController::class, 'update'])
                    ->middleware('role:institution_admin')
                    ->name('payments.update');
                Route::delete('/payments/{payment}', [InstitutionPaymentController::class, 'destroy'])
                    ->middleware('role:institution_admin')
                    ->name('payments.destroy');
                Route::post('/debts/{statement}/quick-pay', [InstitutionPaymentController::class, 'quickPay'])
                    ->middleware('role:institution_admin')
                    ->name('debts.quick-pay');
                Route::get('/debts', [InstitutionDebtController::class, 'index'])
                    ->middleware('role:institution_admin')
                    ->name('debts');
                Route::get('/debts/{statement}', [InstitutionDebtController::class, 'show'])
                    ->middleware('role:institution_admin')
                    ->name('debts.show');
                Route::get('/invoices', [InstitutionInvoiceController::class, 'index'])
                    ->middleware('role:institution_admin,institution_secretary')
                    ->name('invoices');
                Route::get('/invoices/statements/search', [InstitutionInvoiceController::class, 'searchStatements'])
                    ->middleware('role:institution_admin')
                    ->name('invoices.statements.search');
                Route::get('/invoices/statements/{statement}/preview', [InstitutionInvoiceController::class, 'previewStatement'])
                    ->middleware('role:institution_admin')
                    ->name('invoices.statements.preview');
                Route::get('/invoices/create', [InstitutionInvoiceController::class, 'create'])
                    ->middleware('role:institution_admin')
                    ->name('invoices.create');
                Route::post('/invoices', [InstitutionInvoiceController::class, 'store'])
                    ->middleware('role:institution_admin')
                    ->name('invoices.store');
                Route::post('/invoices/sync', [InstitutionInvoiceController::class, 'sync'])
                    ->middleware('role:institution_admin')
                    ->name('invoices.sync');
                Route::get('/invoices/{invoice}', [InstitutionInvoiceController::class, 'show'])
                    ->middleware('role:institution_admin,institution_secretary')
                    ->name('invoices.show');
                Route::get('/invoices/{invoice}/download', [InstitutionInvoiceController::class, 'download'])
                    ->middleware('role:institution_admin')
                    ->name('invoices.download');
                Route::get('/invoices/{invoice}/download-cancellation', [InstitutionInvoiceController::class, 'downloadCancellation'])
                    ->middleware('role:institution_admin')
                    ->name('invoices.download-cancellation');
                Route::post('/invoices/{invoice}/reload-pdf', [InstitutionInvoiceController::class, 'reloadPdf'])
                    ->middleware('role:institution_admin')
                    ->name('invoices.reload-pdf');
                Route::post('/invoices/{invoice}/reload-cancellation-pdf', [InstitutionInvoiceController::class, 'reloadCancellationPdf'])
                    ->middleware('role:institution_admin')
                    ->name('invoices.reload-cancellation-pdf');
                // A tényleges "ki jogosult sztornózni" döntést a controller
                // (InstitutionInvoiceController::cancel()) hozza meg
                // User::hasPermission(InstitutionInvoice::PERMISSION_CANCEL) alapján
                // (super_admin/institution_admin mindig igen, institution_secretary
                // csak dedikált jogosultsággal) - ez a role: middleware csak azt
                // dönti el, ki juthat el egyáltalán idáig. A 'super_admin' szerepel
                // itt, de MA MÉG NEM ér el ide: a fenti, ezt az egészet befoglaló
                // 'institution-context'+'role:' kapu (routes/web.php kb. 228. sor)
                // nem engedi be a super_admin-t egyetlen /institution-admin/* oldalra
                // sem - ez egy jelenlegi, a számlázástól független architekturális
                // hiányosság, amit szándékosan NEM oldottunk meg itt (ld. a záró
                // jelentés vonatkozó pontja), nehogy egy szélesebb, kockázatosabb
                // routing-átalakítást indítsunk útjára a 2. fázis keretében.
                Route::post('/invoices/{invoice}/cancel', [InstitutionInvoiceController::class, 'cancel'])
                    ->middleware('role:institution_admin,institution_secretary,super_admin')
                    ->name('invoices.cancel');
                Route::delete('/invoices/{invoice}', [InstitutionInvoiceController::class, 'destroy'])
                    ->middleware('role:institution_admin')
                    ->name('invoices.destroy');
                Route::get('/exports', [InstitutionFinanceExportController::class, 'index'])
                    ->middleware('role:institution_admin')
                    ->name('exports');
                Route::post('/exports/download', [InstitutionFinanceExportController::class, 'download'])
                    ->middleware('role:institution_admin')
                    ->name('exports.download');
            });

        Route::middleware('role:institution_admin')
            ->prefix('institution-admin/payment-obligations')
            ->name('institution.payment-obligations.')
            ->group(function () {
                Route::get('/', [PaymentObligationController::class, 'index'])->name('index');
                Route::post('/recalculate', [PaymentObligationController::class, 'recalculate'])->name('recalculate');
                Route::post('/close', [PaymentObligationController::class, 'close'])->name('close');
                Route::post('/reopen', [PaymentObligationController::class, 'reopen'])->name('reopen');
                Route::get('/monthly-summary/{year}/{month}/export', [PaymentObligationController::class, 'exportMonthlySummary'])
                    ->whereNumber('year')
                    ->whereNumber('month')
                    ->name('monthly-summary.export');
                Route::get('/monthly-summary/{year}/{month}/print', [PaymentObligationController::class, 'printMonthlySummary'])
                    ->whereNumber('year')
                    ->whereNumber('month')
                    ->name('monthly-summary.print');
                Route::get('/{statement}', [PaymentObligationController::class, 'show'])->name('show');
                Route::get('/{child}/{year}/{month}/export', [PaymentObligationController::class, 'exportChildMonthlyDetails'])
                    ->whereNumber('year')
                    ->whereNumber('month')
                    ->name('export');
                Route::put('/{statement}/invoice', [PaymentObligationController::class, 'updateInvoice'])->name('invoice.update');
                Route::put('/{statement}/days/{day}', [PaymentObligationController::class, 'updateDay'])->name('days.update');
                Route::post('/{statement}/days/{day}/reset', [PaymentObligationController::class, 'resetDay'])->name('days.reset');
                Route::get('/{statement}/adjustments', [FinancialAdjustmentController::class, 'index'])->name('adjustments.index');
                Route::post('/{statement}/adjustments', [FinancialAdjustmentController::class, 'store'])->name('adjustments.store');
                Route::put('/{statement}/adjustments/{adjustment}/reverse', [FinancialAdjustmentController::class, 'reverse'])->name('adjustments.reverse');
            });

        Route::middleware('role:institution_admin')
            ->prefix('institution-admin/employee-payment-obligations')
            ->name('institution.employee-payment-obligations.')
            ->group(function () {
                Route::get('/', [EmployeePaymentObligationController::class, 'index'])->name('index');
                Route::post('/recalculate', [EmployeePaymentObligationController::class, 'recalculate'])->name('recalculate');
                Route::post('/close', [EmployeePaymentObligationController::class, 'close'])->name('close');
                Route::post('/reopen', [EmployeePaymentObligationController::class, 'reopen'])->name('reopen');
            });

        /*
        |--------------------------------------------------------------------------
        | Riportok
        |--------------------------------------------------------------------------
        */
        Route::prefix('institution/reports')
            ->name('institution.reports.')
            ->group(function () {
                Route::get('/', [ReportController::class, 'index'])->name('index');
            });

        /*
        |--------------------------------------------------------------------------
        | Kézikönyv
        |--------------------------------------------------------------------------
        */
        Route::get('/institution-admin/handbook', [HandbookController::class, 'index'])
            ->name('institution.handbook');
    });
});

Route::middleware(['auth', 'role:meal_kiosk', 'barcode-entry-enabled'])->prefix('kiosk')->name('kiosk.')->group(function () {
    Route::get('/', [MealKioskController::class, 'show'])->name('show');
    Route::post('/session/meal-type', [MealKioskController::class, 'updateMealType'])->name('meal-type.update');
    Route::get('/session', [MealKioskController::class, 'sessionData'])->name('session');
    Route::post('/scan', [MealKioskController::class, 'scan'])->name('scan');
    Route::post('/lock', [MealKioskController::class, 'lock'])->name('lock');
});
