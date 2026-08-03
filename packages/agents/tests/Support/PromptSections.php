<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Support;

final readonly class PromptSections
{
    /** @return list<string> */
    public static function toolNames(string $prompt): array {
        $section = self::between($prompt, 'Available tools:', 'In addition to the tools above');
        $names = [];
        foreach (explode("\n", $section) as $line) {
            if (preg_match('/^- ([^:]+): /', trim($line), $matches) !== 1) {
                continue;
            }
            $names[] = $matches[1];
        }
        return $names;
    }

    /** @return list<string> */
    public static function guidelines(string $prompt): array {
        $section = self::after($prompt, 'Guidelines:');
        $guidelines = [];
        foreach (explode("\n", $section) as $line) {
            $trimmed = trim($line);
            if (!str_starts_with($trimmed, '- ')) {
                if ($guidelines !== []) {
                    break;
                }
                continue;
            }
            $guidelines[] = substr($trimmed, 2);
        }
        return $guidelines;
    }

    private static function between(string $text, string $start, string $end): string {
        $tail = self::after($text, $start);
        $position = strpos($tail, $end);
        return $position === false ? $tail : substr($tail, 0, $position);
    }

    private static function after(string $text, string $marker): string {
        $position = strpos($text, $marker);
        return $position === false ? '' : substr($text, $position + strlen($marker));
    }
}
