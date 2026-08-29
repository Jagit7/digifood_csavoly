<?php

namespace App\Support;

use Illuminate\Support\Arr;

class CibMessage
{
    /**
     * @param  array<string, scalar|null>  $fields
     */
    public static function build(array $fields): string
    {
        $parts = [];

        foreach ($fields as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $parts[] = $key.'='.(string) $value;
        }

        return implode('&', $parts);
    }

    /**
     * @return array<string, string>
     */
    public static function parse(string $message): array
    {
        if ($message === '') {
            return [];
        }

        parse_str($message, $parsed);

        return collect($parsed)
            ->mapWithKeys(fn ($value, $key) => [$key => is_array($value) ? (string) Arr::first($value) : (string) $value])
            ->all();
    }
}
