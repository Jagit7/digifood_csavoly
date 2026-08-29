<?php

namespace App\Support\Finance;

final class PaymentComponent
{
    public const FOUNDATION = 'foundation';

    public const KINDERGARTEN = 'kindergarten';

    public const LEGACY = 'legacy';

    public static function labels(): array
    {
        return [
            self::FOUNDATION => 'Zsárica Alapítvány',
            self::KINDERGARTEN => 'Óvodai étkezési díj',
            self::LEGACY => 'Havi étkezési díj',
        ];
    }

    public static function transferLabels(): array
    {
        return [
            self::FOUNDATION => 'Zsárica Alapítvány számla',
            self::KINDERGARTEN => 'Óvodai számla',
        ];
    }

    public static function all(): array
    {
        return [
            self::FOUNDATION,
            self::KINDERGARTEN,
        ];
    }
}
