<?php

namespace App\Http\Controllers\Dashboard\InstitutionAdmin\Finance;

use App\Http\Controllers\Controller;
use App\Models\DiscountType;
use App\Models\Institution;
use App\Models\InstitutionMealPackage;
use App\Models\InstitutionPayment;
use App\Models\InstitutionSetting;
use App\Support\Finance\InstitutionFinanceHelpContent;
use Illuminate\View\View;

class InstitutionFinanceHelpController extends Controller
{
    public function __construct(
        private readonly InstitutionFinanceHelpContent $content
    ) {}

    public function index(): View
    {
        $institution = $this->institution();
        $setting = InstitutionSetting::firstOrCreate(
            ['institution_id' => $institution->id],
            InstitutionSetting::defaults()
        );
        $defaultMealPackage = InstitutionMealPackage::query()
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->where('is_default', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->first();
        $discountTypes = DiscountType::query()
            ->where('institution_id', $institution->id)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('dashboard.institution_admin.finance.help.index', [
            'institution' => $institution,
            'setting' => $setting,
            'defaultMealPackage' => $defaultMealPackage,
            'discountTypes' => $discountTypes,
            'content' => $this->content->build(
                $institution,
                $setting,
                $defaultMealPackage,
                $discountTypes,
                InstitutionSetting::invoicingProviderOptions(),
                InstitutionSetting::cardPaymentProviderOptions(),
                InstitutionPayment::paymentMethodOptions()
            ),
        ]);
    }

    private function institution(): Institution
    {
        return $this->currentAdminInstitution();
    }
}
