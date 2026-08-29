<?php

namespace App\Support;

use RuntimeException;

class CibMessageCrypto
{
    public function encrypt(string $message, string $pid, string $secret): string
    {
        ['key' => $key, 'iv' => $iv] = $this->resolveKeyMaterial($secret);

        $normalized = rawurlencode($message);
        $normalized = str_replace(['%3D', '%26'], ['=', '&'], $normalized);
        $normalizedWithCrc = $normalized.$this->crcBytes($normalized);
        $cipherText = openssl_encrypt($normalizedWithCrc, 'DES-EDE3-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($cipherText === false) {
            throw new RuntimeException('A CIB üzenet titkosítása sikertelen.');
        }

        $padded = $cipherText;
        $pad = 3 - (strlen($padded) % 3);

        if ($pad === 0) {
            $pad = 3;
        }

        $padded .= str_repeat(chr($pad), $pad);

        return CibMessage::build([
            'PID' => $pid,
            'CRYPTO' => '1',
            'DATA' => rawurlencode(base64_encode($padded)),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function decrypt(string $encryptedMessage, string $secret): array
    {
        $outer = CibMessage::parse($encryptedMessage);
        $data = $outer['DATA'] ?? null;

        if (($outer['PID'] ?? null) === null || $data === null) {
            throw new RuntimeException('A CIB válasz nem tartalmazza a kötelező PID/DATA mezőket.');
        }

        ['key' => $key, 'iv' => $iv] = $this->resolveKeyMaterial($secret);

        $decoded = base64_decode(rawurldecode($data), true);

        if ($decoded === false || $decoded === '') {
            throw new RuntimeException('A CIB válasz base64 dekódolása sikertelen.');
        }

        $last = ord(substr($decoded, -1));
        $unpadded = $decoded;

        if ($last > 0 && $last <= 3 && strlen($decoded) >= $last) {
            $tail = substr($decoded, -1 * $last);
            if ($tail === str_repeat(chr($last), $last)) {
                $unpadded = substr($decoded, 0, -1 * $last);
            }
        }

        $cleartext = openssl_decrypt($unpadded, 'DES-EDE3-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($cleartext === false || strlen($cleartext) < 4) {
            throw new RuntimeException('A CIB válasz 3DES visszafejtése sikertelen.');
        }

        $crcBytes = substr($cleartext, -4);
        $payload = substr($cleartext, 0, -4);

        if (! hash_equals($this->crcBytes($payload), $crcBytes)) {
            throw new RuntimeException('A CIB válasz CRC ellenőrzése sikertelen.');
        }

        $payload = rawurldecode(str_replace(['&', '='], ['%26', '%3D'], $payload));

        return CibMessage::parse($payload);
    }

    /**
     * @return array{key: string, iv: string}
     */
    private function resolveKeyMaterial(string $secret): array
    {
        $keyInfo = CibSecretKey::fromBase64($secret);
        $key1 = substr($keyInfo, 0, 8);
        $key2 = substr($keyInfo, 8, 8);
        $iv = substr($keyInfo, 16, 8);

        return [
            'key' => $key1.$key2.$key1,
            'iv' => $iv,
        ];
    }

    private function crcBytes(string $payload): string
    {
        $crc = strtoupper(str_pad(dechex(crc32($payload)), 8, '0', STR_PAD_LEFT));
        $bytes = '';

        for ($i = 0; $i < 4; $i++) {
            $bytes .= chr((int) hexdec(substr($crc, $i * 2, 2)));
        }

        return $bytes;
    }
}
