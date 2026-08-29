<?php

namespace App\Support;

use RuntimeException;

class CibSecretKey
{
    public static function extractTrailingKeyBytes(string $bytes): string
    {
        if (strlen($bytes) < 24) {
            throw new RuntimeException('A CIB kulcsfájl túl rövid: legalább 24 bájtnak kell rendelkezésre állnia.');
        }

        return substr($bytes, -24);
    }

    public static function fromFile(string $path): string
    {
        $normalizedPath = trim($path);

        if ($normalizedPath === '') {
            throw new RuntimeException('A CIB kulcsfájl elérési útja hiányzik.');
        }

        if (! is_file($normalizedPath) || ! is_readable($normalizedPath)) {
            throw new RuntimeException('A megadott CIB kulcsfájl nem olvasható.');
        }

        $contents = file_get_contents($normalizedPath);

        if ($contents === false) {
            throw new RuntimeException('A CIB kulcsfájl beolvasása sikertelen.');
        }

        return self::extractTrailingKeyBytes($contents);
    }

    public static function toBase64FromFile(string $path): string
    {
        return base64_encode(self::fromFile($path));
    }

    public static function fromBase64(string $base64): string
    {
        $normalized = trim($base64);

        if ($normalized === '') {
            throw new RuntimeException('A CIB kulcsanyag hiányzik.');
        }

        $decoded = base64_decode($normalized, true);

        if ($decoded === false) {
            throw new RuntimeException('A CIB kulcsanyag Base64 dekódolása sikertelen.');
        }

        return self::extractTrailingKeyBytes($decoded);
    }
}
