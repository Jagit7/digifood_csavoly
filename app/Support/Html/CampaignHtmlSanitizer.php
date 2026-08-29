<?php

namespace App\Support\Html;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Egyszerű, külső csomagtól független (nincs composer install-hoz kötve)
 * allowlist-alapú HTML tisztító az intézményi admin által szabadon
 * szerkesztett e-mail kampány szövegekhez (EmailCampaign::body).
 *
 * A cél NEM egy teljes HTML5-kompatibilis tisztító, hanem a tárolt XSS
 * (CWE-79) kizárása: minden <script>, esemény-attribútum (onclick,
 * onerror, stb.), javascript:/vbscript: URL, <iframe>/<object>/<embed>/
 * <form>/<style> és hasonló veszélyes elem eltávolítása, mielőtt a
 * szöveg akár az admin felületen ({!! !!}), akár a kiküldött e-mailben
 * (InstitutionCampaignMail) megjelenik. Nem engedélyezett elemnél a
 * szöveges tartalom megmarad, csak maga a (potenciálisan veszélyes)
 * címke tűnik el - ez a legkevésbé meglepő viselkedés egy admin által
 * beírt, jóhiszemű megfogalmazás esetén is.
 */
class CampaignHtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'span', 'div',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'blockquote', 'hr',
        'a', 'img',
        'table', 'thead', 'tbody', 'tr', 'td', 'th',
    ];

    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'target'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        '*' => ['style'],
    ];

    public static function clean(?string $html): string
    {
        $html = (string) $html;

        if (trim($html) === '') {
            return '';
        }

        $previousSetting = libxml_use_internal_errors(true);

        $document = new DOMDocument('1.0', 'UTF-8');
        // A tartalmat egy ideiglenes gyökér elembe csomagoljuk, hogy a
        // DOMDocument ne próbáljon teljes HTML dokumentumot (html/head/
        // body) építeni belőle, és UTF-8-ként értelmezze a bemenetet.
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="digifood-sanitize-root">'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previousSetting);

        $root = $document->getElementById('digifood-sanitize-root');

        if (! $root) {
            return '';
        }

        self::sanitizeChildren($root, $document);

        $output = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $document->saveHTML($child);
        }

        return $output;
    }

    private static function sanitizeChildren(DOMNode $node, DOMDocument $document): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }

            if ($child instanceof DOMComment || ! $child instanceof DOMElement) {
                $node->removeChild($child);

                continue;
            }

            $tagName = strtolower($child->tagName);

            // <script>, <style>, <iframe>, <object>, <embed>, <form> stb.:
            // a teljes elemet a tartalmával együtt eltávolítjuk, mert
            // ezeknél magának a szövegtörzsnek sincs értelme (pl. egy
            // <script> tag belseje nyers JS kód lenne).
            if (in_array($tagName, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'link', 'meta', 'svg'], true)) {
                $node->removeChild($child);

                continue;
            }

            if (! in_array($tagName, self::ALLOWED_TAGS, true)) {
                self::sanitizeChildren($child, $document);

                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }

                $node->removeChild($child);

                continue;
            }

            self::sanitizeAttributes($child, $tagName);
            self::sanitizeChildren($child, $document);
        }
    }

    private static function sanitizeAttributes(DOMElement $element, string $tagName): void
    {
        $allowed = array_merge(
            self::ALLOWED_ATTRIBUTES[$tagName] ?? [],
            self::ALLOWED_ATTRIBUTES['*'] ?? []
        );

        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            $name = strtolower($attribute->name);

            if (str_starts_with($name, 'on') || ! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->name);

                continue;
            }

            if (in_array($name, ['href', 'src'], true) && ! self::isSafeUrl($attribute->value)) {
                $element->removeAttribute($attribute->name);

                continue;
            }

            if ($name === 'style' && self::isUnsafeStyle($attribute->value)) {
                $element->removeAttribute($attribute->name);
            }
        }

        if ($tagName === 'a') {
            $element->setAttribute('rel', 'noopener noreferrer nofollow');
        }
    }

    private static function isSafeUrl(string $value): bool
    {
        $value = trim($value);

        if ($value === '' || str_starts_with($value, '#') || str_starts_with($value, '/')) {
            return true;
        }

        if (str_starts_with($value, 'data:image/')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true);
    }

    private static function isUnsafeStyle(string $value): bool
    {
        $normalized = strtolower(preg_replace('/\s+/', '', $value) ?? '');

        return str_contains($normalized, 'javascript:')
            || str_contains($normalized, 'expression(')
            || str_contains($normalized, 'url(');
    }
}
