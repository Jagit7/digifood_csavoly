<?php

namespace App\Support\PaymentObligation;

use App\Models\PaymentObligation\MonthlyPaymentDay;
use Illuminate\Support\Str;

class MonthlyPaymentDayStatus
{
    public static function meta(?string $status): array
    {
        $meta = self::map()[$status ?? ''] ?? null;

        if ($meta !== null) {
            return $meta;
        }

        return [
            'label' => Str::of((string) $status)->replace('_', ' ')->lower()->ucfirst()->toString(),
            'badge_class' => 'bg-secondary',
            'description' => 'A nap állapota nincs külön részletezve.',
        ];
    }

    public static function map(): array
    {
        return [
            MonthlyPaymentDay::STATUS_NO_ACTIVE_MEAL => [
                'label' => 'Nincs aktív étkezés',
                'badge_class' => 'bg-secondary',
                'description' => 'A gyermeknek ezen a napon nincs aktív étkezési beállítása vagy választható menüje.',
            ],
            MonthlyPaymentDay::STATUS_PAY => [
                'label' => 'Fizetendő',
                'badge_class' => 'bg-success',
                'description' => 'Az adott nap étkezése fizetendő tételként szerepel.',
            ],
            MonthlyPaymentDay::STATUS_CANCELLED_IN_ADVANCE => [
                'label' => 'Időben lemondva',
                'badge_class' => 'bg-info text-dark',
                'description' => 'A gyermek étkezése a fizetési határnap előtt le lett mondva.',
            ],
            MonthlyPaymentDay::STATUS_CANCELLED_AFTER_CLOSING => [
                'label' => 'Határidő után lemondva',
                'badge_class' => 'bg-warning text-dark',
                'description' => 'A lemondás a fizetési határnap után érkezett be.',
            ],
            MonthlyPaymentDay::STATUS_SCHOOL_BREAK => [
                'label' => 'Iskolai szünet',
                'badge_class' => 'bg-primary',
                'description' => 'A nap iskolai szünet vagy tanítás nélküli nap miatt nem számolható étkezési napként.',
            ],
            MonthlyPaymentDay::STATUS_WEEKEND => [
                'label' => 'Hétvége',
                'badge_class' => 'bg-light text-dark border',
                'description' => 'A nap hétvégére esik, ezért nincs normál étkezési kötelezettség.',
            ],
            MonthlyPaymentDay::STATUS_WORKING_SATURDAY => [
                'label' => 'Tanítási szombat',
                'badge_class' => 'bg-info text-dark',
                'description' => 'A nap hétvégére esik, de külön tanítási napként van beállítva.',
            ],
            MonthlyPaymentDay::STATUS_ABSENCE => [
                'label' => 'Hiányzás',
                'badge_class' => 'bg-warning text-dark',
                'description' => 'A gyermek hiányzása miatt az étkezés eltérően kezelendő.',
            ],
            MonthlyPaymentDay::STATUS_NO_VALID_PRICE => [
                'label' => 'Nincs érvényes ár',
                'badge_class' => 'bg-danger',
                'description' => 'Az adott nap étkezéséhez nem található teljes, érvényes árbeállítás.',
            ],
            MonthlyPaymentDay::STATUS_CLASS_CANCELLATION => [
                'label' => 'Csoportszintű lemondás',
                'badge_class' => 'bg-warning text-dark',
                'description' => 'Az étkezés csoportszintű vagy osztályszintű lemondás miatt marad el.',
            ],
            MonthlyPaymentDay::STATUS_FREE_MEAL => [
                'label' => 'Ingyenes étkezés',
                'badge_class' => 'bg-success',
                'description' => 'A kedvezmény miatt ezen a napon nem keletkezik fizetendő összeg.',
            ],
            MonthlyPaymentDay::STATUS_MANUALLY_MODIFIED => [
                'label' => 'Kézzel módosítva',
                'badge_class' => 'bg-dark',
                'description' => 'A napi tételt kézi beavatkozással módosították.',
            ],
        ];
    }
}
