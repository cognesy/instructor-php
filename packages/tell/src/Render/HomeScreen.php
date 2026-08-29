<?php

declare(strict_types=1);

namespace Cognesy\Tell\Render;

use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The no-prompt screen written for a person rather than for a parser.
 *
 * It carries exactly what the structured form carries - where the turn would
 * run, what it would run with, and what to type next. Only the shape differs,
 * so the two never drift into telling different stories about the workspace.
 */
final readonly class HomeScreen
{
    public function __construct(private OutputInterface $output) {}

    /** @param array<string, mixed> $payload */
    public function write(array $payload): void
    {
        $binary = $this->text($payload, 'bin', 'tell');
        $this->line($this->paint($binary, Color::BOLD).' — '.$this->text($payload, 'description'));
        $this->line('');
        $this->facts($payload);
        $this->agents($payload);
        $this->next($payload, $binary);
    }

    /** @param array<string, mixed> $payload */
    private function facts(array $payload): void
    {
        $storage = is_array($payload['storage'] ?? null) ? $payload['storage'] : [];
        $workspace = $payload['workspace'] ?? null;
        foreach ([
            'directory' => $this->text($payload, 'directory'),
            // A missing workspace is the common case and is not a problem, so it
            // reads as a state rather than as a null. What to do about it is
            // already one of the next actions below.
            'workspace' => is_array($workspace)
                ? $this->text($workspace, 'root', 'initialized')
                : 'none (turns run stateless)',
            'storage' => $this->text($storage, 'home'),
            'traces' => $this->text($storage, 'executionTraces'),
        ] as $name => $value) {
            $this->line('  '.$this->paint(str_pad($name, 10), Color::DARK_GRAY).$value);
        }
        $this->line('');
    }

    /** @param array<string, mixed> $payload */
    private function agents(array $payload): void
    {
        $agents = is_array($payload['agents'] ?? null) ? $payload['agents'] : [];
        $this->line($this->paint('Agents', Color::BOLD).' '.$this->paint('('.count($agents).')', Color::DARK_GRAY));
        $width = 0;
        foreach ($agents as $agent) {
            $width = is_array($agent) ? max($width, mb_strlen($this->text($agent, 'name'))) : $width;
        }
        foreach ($agents as $agent) {
            if (! is_array($agent)) {
                continue;
            }
            $name = $this->text($agent, 'name');
            $this->line(
                '  '.$this->paint($name, Color::CYAN)
                .str_repeat(' ', $width - mb_strlen($name) + 2).$this->text($agent, 'description'),
            );
        }
        $errors = $payload['discoveryErrors'] ?? 0;
        if (is_int($errors) && $errors > 0) {
            $this->line('  '.$this->paint($errors.' definition'.($errors === 1 ? '' : 's').' could not be read', Color::YELLOW));
        }
        $this->line('');
    }

    /**
     * The structured screen states its next actions as sentences. Reprinting
     * them keeps one source of truth, with the command itself lifted out so the
     * eye finds what to type.
     *
     * @param  array<string, mixed>  $payload
     */
    private function next(array $payload, string $binary): void
    {
        $help = is_array($payload['help'] ?? null) ? $payload['help'] : [];
        $this->line($this->paint('Next', Color::BOLD));
        foreach ($help as $entry) {
            // Entries without a command are notes about the structured payload's
            // own field names, which say nothing to a reader of this screen.
            if (! is_string($entry) || ! str_contains($entry, '`')) {
                continue;
            }
            $this->line('  '.$this->highlight($entry, $binary));
        }
    }

    /** Paint the backticked command in a help sentence, and drop the backticks. */
    private function highlight(string $entry, string $binary): string
    {
        return (string) preg_replace_callback(
            '/`([^`]+)`/',
            fn (array $match): string => $this->paint(
                str_starts_with($match[1], 'tell') ? $binary.substr($match[1], strlen('tell')) : $match[1],
                Color::CYAN,
            ),
            $entry,
        );
    }

    /** @param array<array-key, mixed> $source */
    private function text(array $source, string $key, string $fallback = ''): string
    {
        $value = $source[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $fallback;
    }

    private function paint(string $text, string $color): string
    {
        return $this->output->isDecorated() ? $color.$text.Color::RESET : $text;
    }

    /** OUTPUT_RAW: the line carries its own escape sequences and arbitrary paths. */
    private function line(string $text): void
    {
        $this->output->write($text."\n", false, OutputInterface::OUTPUT_RAW);
    }
}
