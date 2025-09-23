<?php declare(strict_types=1);

namespace Cognesy\Schema\Utils;

use Cognesy\Pipeline\Enums\ErrorStrategy;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\StateContracts\CanCarryState;

class DocstringUtils
{
    public static function descriptionsOnly(string $code): string {
        $pipeline = Pipeline::builder(ErrorStrategy::FailFast)
            ->through(fn($code) => self::removeMarkers($code))
            ->through(fn($code) => self::removeAnnotations($code))
            ->finally(fn(CanCarryState $state) => trim($state->value()))
            ->create();

        return $pipeline
            ->executeWith(ProcessingState::with($code))
            ->value();
    }

    public static function getParameterDescription(string $name, string $text): string {
        $pattern = '/@param\s+' . $name . '\s+(.*)/';
        if (preg_match($pattern, $text, $matches)) {
            return $matches[1];
        }
        return '';
    }

    private static function removeMarkers(string $code): string {
        // Pattern to match comment markers
        $pattern = '/(\/\*\*|\*\/|\/\/|#)/';

        // Remove comment markers from the string
        $cleanedString = preg_replace($pattern, '', $code);

        // Optional: Clean up extra asterisks and whitespace from multiline comments
        return preg_replace('/^\s*\*\s?/m', '', $cleanedString);
    }

    private static function removeAnnotations(string $code): string {
        $lines = explode("\n", $code);
        $cleanedLines = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                continue;
            }
            if ($trimmed[0] !== '@') {
                $cleanedLines[] = $line;
            }
        }
        return implode("\n", $cleanedLines);
    }
}
