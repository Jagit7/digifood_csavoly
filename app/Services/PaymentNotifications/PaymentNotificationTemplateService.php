<?php

namespace App\Services\PaymentNotifications;

class PaymentNotificationTemplateService
{

    public function defaultSubject(): string
    {
        return 'Tájékoztatás fizetendő étkezési térítési díjról';
    }

    public function defaultBody(): string
    {
        return implode("\n", [
            'Tisztelt Szülő!',
            '',
            'Tájékoztatjuk, hogy elkészült az aktuális étkezési térítési díj elszámolása.',
            '',
            'A fizetendő összeget és az elszámolás részleteit a Digifood szülői felületén tekintheti meg.',
            '',
            'Kérjük, hogy a fizetési határidőig szíveskedjen rendezni a fizetendő összeget.',
            '',
            'Köszönjük együttműködését!',
        ]);
    }

    public function normalizeSubject(?string $subject): ?string
    {
        $subject = trim(strip_tags((string) $subject));

        return $subject === '' ? null : mb_substr($subject, 0, 191);
    }

    public function normalizeBody(?string $body): ?string
    {
        $body = trim(str_replace(["\r\n", "\r"], "\n", (string) $body));

        return $body === '' ? null : $body;
    }

    public function resolvedSubject(?string $customSubject): string
    {
        return $this->normalizeSubject($customSubject) ?? $this->defaultSubject();
    }

    public function resolvedBody(?string $customBody): string
    {
        return $this->normalizeBody($customBody) ?? $this->defaultBody();
    }
}
