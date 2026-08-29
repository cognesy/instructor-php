<?php

declare(strict_types=1);

namespace Cognesy\Tell\Render;

use JsonException;

/**
 * One tool invocation reduced to what a reader needs to follow it.
 *
 * Each known tool surfaces its salient argument - the command for a shell, the
 * path for a file operation - instead of a raw JSON dump, so a trace reads as
 * work being done rather than as wire traffic. Unknown tools keep the JSON
 * fallback because there is nothing better to guess.
 */
final readonly class ToolCallView
{
    private function __construct(
        public string $label,
        public string $detail,
        public string $body,
        public string $language,
    ) {}

    /** @param array<array-key, mixed> $args */
    public static function forCall(string $tool, array $args): self {
        return match ($tool) {
            'shell', 'bash' => new self(
                'shell',
                self::bracketed(self::text($args, 'description')),
                self::text($args, 'command'),
                'sh',
            ),
            'read', 'read_file' => new self('read', self::quoted(self::text($args, 'path')), '', ''),
            'write', 'write_file' => self::write($args),
            'apply_patch' => new self('patch', '', self::text($args, 'patch'), 'diff'),
            'edit' => new self(
                'edit',
                self::quoted(self::text($args, 'path')),
                self::replacement($args),
                'diff',
            ),
            'ask_user' => new self('ask', self::text($args, 'question'), '', ''),
            default => new self($tool, self::json($args), '', ''),
        };
    }

    /** @param array<array-key, mixed> $args */
    private static function write(array $args): self {
        $path = self::text($args, 'path');
        $content = self::text($args, 'content');
        $bytes = strlen($content);

        return new self(
            'write',
            trim(self::quoted($path) . ' (' . $bytes . ' byte' . ($bytes === 1 ? '' : 's') . ')'),
            $content,
            self::languageFor($path),
        );
    }

    /**
     * An edit is a replacement, so it is shown the way a diff shows one: the
     * text that goes away above the text that arrives.
     *
     * @param  array<array-key, mixed>  $args
     */
    private static function replacement(array $args): string {
        $old = self::text($args, 'old_string');
        $new = self::text($args, 'new_string');
        $lines = [];
        foreach (self::lines($old) as $line) {
            $lines[] = '- ' . $line;
        }
        foreach (self::lines($new) as $line) {
            $lines[] = '+ ' . $line;
        }

        return implode("\n", $lines);
    }

    /** @return list<string> */
    private static function lines(string $text): array {
        return $text === '' ? [] : explode("\n", $text);
    }

    /**
     * The language only decides how a body is labelled, so an unrecognised
     * extension is left blank rather than guessed at.
     */
    private static function languageFor(string $path): string {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'php' => 'php',
            'json' => 'json',
            'md', 'markdown' => 'md',
            'yml', 'yaml' => 'yaml',
            'sh', 'bash' => 'sh',
            default => '',
        };
    }

    /** @param array<array-key, mixed> $args */
    private static function text(array $args, string $key): string {
        $value = $args[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    private static function bracketed(string $value): string {
        return $value === '' ? '' : '[' . $value . ']';
    }

    private static function quoted(string $value): string {
        return $value === '' ? '' : '`' . $value . '`';
    }

    /** @param array<array-key, mixed> $args */
    private static function json(array $args): string {
        if ($args === []) {
            return '';
        }
        try {
            return json_encode($args, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return '<unencodable arguments>';
        }
    }
}
