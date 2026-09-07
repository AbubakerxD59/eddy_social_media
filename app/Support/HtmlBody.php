<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

final class HtmlBody
{
    /**
     * @var list<string>
     */
    private const ALLOWED_TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'a'];

    /**
     * @var list<string>
     */
    private const STRIP_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'textarea', 'svg', 'math', 'link', 'meta'];

    public static function sanitize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('/<[a-z][\s\S]*>/i', $value)) {
            return $value;
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="eddy-root">'.$value.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = (new DOMXPath($document))->query('//*[@id="eddy-root"]')->item(0);

        if (! $root instanceof DOMElement) {
            $plain = self::plainText($value);

            return $plain === '' ? null : $plain;
        }

        self::scrub($root);

        $html = '';

        foreach ($root->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        $html = trim($html);
        $plain = self::plainText($html);

        if ($plain === '') {
            return null;
        }

        return $html;
    }

    public static function present(?string $value): ?string
    {
        return self::sanitize($value);
    }

    public static function plainText(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $withBreaks = str_ireplace(
            ['<br>', '<br/>', '<br />', '</p>', '</li>'],
            ["\n", "\n", "\n", "\n", "\n"],
            $value,
        );

        $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace("/[ \t]+\n/", "\n", $text);
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    public static function isRich(?string $value): bool
    {
        return is_string($value) && preg_match('/<(p|br|strong|b|em|i|u|s|ul|ol|li|a)\b/i', $value) === 1;
    }

    private static function scrub(DOMNode $node): void
    {
        $children = [];

        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }

        foreach ($children as $child) {
            if (! $child instanceof DOMElement) {
                if (! $child instanceof \DOMText) {
                    $node->removeChild($child);
                }

                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::STRIP_TAGS, true)) {
                $node->removeChild($child);

                continue;
            }

            self::scrub($child);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }

                $node->removeChild($child);

                continue;
            }

            self::cleanAttributes($child);
        }
    }

    private static function cleanAttributes(DOMElement $element): void
    {
        $allowedHref = null;

        if (strtolower($element->tagName) === 'a') {
            $href = trim($element->getAttribute('href'));

            if (preg_match('/^(https?:\/\/|mailto:)/i', $href) === 1) {
                $allowedHref = $href;
            }
        }

        $names = [];

        foreach ($element->attributes ?? [] as $attribute) {
            $names[] = $attribute->name;
        }

        foreach ($names as $name) {
            $element->removeAttribute($name);
        }

        if ($allowedHref === null) {
            if (strtolower($element->tagName) === 'a' && $element->parentNode) {
                while ($element->firstChild) {
                    $element->parentNode->insertBefore($element->firstChild, $element);
                }

                $element->parentNode->removeChild($element);
            }

            return;
        }

        $element->setAttribute('href', $allowedHref);
        $element->setAttribute('target', '_blank');
        $element->setAttribute('rel', 'noopener noreferrer nofollow');
    }
}
