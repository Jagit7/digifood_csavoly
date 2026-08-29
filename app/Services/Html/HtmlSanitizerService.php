<?php

namespace App\Services\Html;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

class HtmlSanitizerService
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $allowedTags = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'a' => ['href', 'target', 'rel'],
        'h2' => [],
        'h3' => [],
        'h4' => [],
    ];

    public function sanitize(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><body>'.$html.'</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $body = $document->getElementsByTagName('body')->item(0);

        if (! $body instanceof DOMElement) {
            return '';
        }

        $clean = $this->sanitizeChildren($body, $document);

        return trim($clean);
    }

    private function sanitizeChildren(DOMNode $node, DOMDocument $document): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            $html .= $this->sanitizeNode($child, $document);
        }

        return $html;
    }

    private function sanitizeNode(DOMNode $node, DOMDocument $document): string
    {
        if ($node instanceof DOMText) {
            return htmlspecialchars($node->wholeText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $tag = strtolower($node->tagName);

        if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form'], true)) {
            return '';
        }

        if (! array_key_exists($tag, $this->allowedTags)) {
            return $this->sanitizeChildren($node, $document);
        }

        $attributes = $this->sanitizeAttributes($node, $this->allowedTags[$tag]);
        $content = $this->sanitizeChildren($node, $document);

        if ($tag === 'br') {
            return '<br>';
        }

        return sprintf('<%1$s%2$s>%3$s</%1$s>', $tag, $attributes, $content);
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function sanitizeAttributes(DOMElement $element, array $allowed): string
    {
        $attributes = [];

        foreach ($allowed as $name) {
            if (! $element->hasAttribute($name)) {
                continue;
            }

            $value = trim($element->getAttribute($name));

            if ($value === '') {
                continue;
            }

            if ($name === 'href' && ! $this->isSafeHref($value)) {
                continue;
            }

            if ($name === 'target') {
                $value = $value === '_blank' ? '_blank' : '_self';
            }

            if ($name === 'rel') {
                $value = 'noopener noreferrer';
            }

            $attributes[] = sprintf(
                ' %s="%s"',
                $name,
                htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            );
        }

        if ($element->hasAttribute('target')
            && $element->getAttribute('target') === '_blank'
            && ! collect($allowed)->contains('rel')) {
            $attributes[] = ' rel="noopener noreferrer"';
        }

        return implode('', $attributes);
    }

    private function isSafeHref(string $href): bool
    {
        if (preg_match('/^(https?:|mailto:|\/)/i', $href) === 1) {
            return true;
        }

        return ! str_contains($href, ':');
    }
}
