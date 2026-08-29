<?php

namespace App\Services\Meals;

class AbMenuNotificationTemplateService
{
    public function defaultSubject(): string
    {
        return 'Elindult az A/B menüválasztás';
    }

    public function defaultBody(): string
    {
        return implode("\n", [
            'Tisztelt Szülő!',
            '',
            'Értesítjük, hogy elindult az A/B menüválasztás. Kérjük, az alábbi határidőig válassza ki gyermeke(i) számára az egyes napokra, hogy A vagy B menüt szeretne igénybe venni.',
            '',
            'Ha a határidőig nem érkezik választás egy adott napra, a rendszer alapértelmezetten az A menüt rögzíti.',
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
