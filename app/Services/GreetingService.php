<?php

namespace App\Services;

use Carbon\Carbon;

class GreetingService
{
    public function greet(string $name): string
    {
        $hour = Carbon::now()->format('H');

        if ($hour >= 5 && $hour <= 9) {
            return "Jó reggelt, {$name}!";
        }

        if ($hour >= 10 && $hour <= 17) {
            return "Jó napot, {$name}!";
        }

        if ($hour >= 18 && $hour <= 21) {
            return "Jó estét, {$name}!";
        }

        return "Jó éjszakát, {$name}!";
    }
}