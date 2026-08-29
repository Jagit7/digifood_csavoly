<?php

namespace App\Services\Meals;

use App\Models\Institution;
use App\Models\InstitutionMealType;
use App\Models\MealCheckIn;
use App\Models\User;
use Illuminate\Database\QueryException;

class MealKioskService
{
    public function __construct(
        private readonly MealEligibilityService $eligibility
    ) {}

    public function scan(
        Institution $institution,
        User $kioskUser,
        InstitutionMealType $mealType,
        string $barcodeToken
    ): array {
        $evaluation = $this->eligibility->evaluate($institution, $kioskUser, $barcodeToken, $mealType);
        $tokenSuffix = mb_substr($barcodeToken, -8);
        $eater = $evaluation['eater'] ?? null;
        $child = $evaluation['child'] ?? null;
        $eaterType = $evaluation['eater_type'] ?? $eater?->getMorphClass();
        $eaterId = $eater?->getKey();

        if ($evaluation['status'] !== 'ok') {
            $record = MealCheckIn::create([
                'institution_id' => $institution->id,
                'child_id' => $child?->id,
                'eater_type' => $eaterType,
                'eater_id' => $eaterId,
                'institution_meal_type_id' => $mealType->id,
                'menu_choice' => null,
                'service_date' => now()->toDateString(),
                'scanned_at' => now(),
                'barcode_token_suffix' => $tokenSuffix,
                'kiosk_user_id' => $kioskUser->id,
                'status' => MealCheckIn::STATUS_REJECTED,
                'rejection_reason' => $evaluation['code'],
                'success_key' => null,
            ]);

            return [
                'result' => 'rejected',
                'message' => $evaluation['message'],
                'code' => $evaluation['code'],
                'record' => $record,
                'child' => $child,
                'eater' => $eater,
            ];
        }

        $successKey = implode(':', [
            $institution->id,
            $eaterType,
            $eaterId,
            $mealType->id,
            $evaluation['service_date'],
        ]);

        try {
            $record = MealCheckIn::create([
                'institution_id' => $institution->id,
                'child_id' => $child?->id,
                'eater_type' => $eaterType,
                'eater_id' => $eaterId,
                'institution_meal_type_id' => $mealType->id,
                'menu_choice' => $evaluation['menu_choice'],
                'service_date' => $evaluation['service_date'],
                'scanned_at' => $evaluation['scanned_at'],
                'barcode_token_suffix' => $tokenSuffix,
                'kiosk_user_id' => $kioskUser->id,
                'status' => MealCheckIn::STATUS_SUCCESS,
                'rejection_reason' => null,
                'success_key' => $successKey,
            ]);

            return [
                'result' => 'success',
                'message' => 'Sikeres beolvasás.',
                'code' => null,
                'record' => $record,
                'child' => $child,
                'eater' => $eater,
                'menu_choice' => $evaluation['menu_choice'],
                'meal_type' => $evaluation['meal_type'],
            ];
        } catch (QueryException) {
            $existing = MealCheckIn::query()
                ->where('success_key', $successKey)
                ->latest('scanned_at')
                ->first();

            $record = MealCheckIn::create([
                'institution_id' => $institution->id,
                'child_id' => $child?->id,
                'eater_type' => $eaterType,
                'eater_id' => $eaterId,
                'institution_meal_type_id' => $mealType->id,
                'menu_choice' => $evaluation['menu_choice'],
                'service_date' => $evaluation['service_date'],
                'scanned_at' => now(),
                'barcode_token_suffix' => $tokenSuffix,
                'kiosk_user_id' => $kioskUser->id,
                'status' => MealCheckIn::STATUS_DUPLICATE,
                'rejection_reason' => 'already_scanned',
                'success_key' => null,
            ]);

            return [
                'result' => 'duplicate',
                'message' => 'Már beolvasva.',
                'code' => 'already_scanned',
                'record' => $record,
                'existing' => $existing,
                'child' => $child,
                'eater' => $eater,
                'menu_choice' => $evaluation['menu_choice'],
                'meal_type' => $evaluation['meal_type'],
            ];
        }
    }
}