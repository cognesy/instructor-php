<?php declare(strict_types=1);

namespace Cognesy\Events\Support;

use Cognesy\Events\Contracts\CanFormatConsoleEvent;
use Cognesy\Events\Data\ConsoleEventLine;
use DateTimeImmutable;

final class ConsoleEventPrinter
{
    private readonly bool $renderColors;

    public function __construct(
        bool $useColors = true,
        private readonly bool $showTimestamps = true,
    ) {
        $this->renderColors = $useColors && $this->supportsColors();
    }

    public function wiretap(CanFormatConsoleEvent $formatter): callable {
        return function (object $event) use ($formatter): void {
            $this->printIfAny($formatter->format($event));
        };
    }

    public function printIfAny(?ConsoleEventLine $line): void {
        if ($line === null) {
            return;
        }

        $timestamp = $this->showTimestamps
            ? (new DateTimeImmutable())->format('H:i:s.v').' '
            : '';

        $context = $line->context !== null
            ? $line->context.' '
            : '';

        $label = $this->colorize(str_pad($line->label, 4), $line->color->ansiCode());

        echo sprintf("%s%s[%s] %s\n", $timestamp, $context, $label, $line->message);
    }

    private function colorize(string $text, string $ansiCode): string {
        if (!$this->renderColors) {
            return $text;
        }

        return "\033[{$ansiCode}m{$text}\033[0m";
    }

    private function supportsColors(): bool {
        if (DIRECTORY_SEPARATOR === '\\') {
            return false !== getenv('ANSICON')
                || 'ON' === getenv('ConEmuANSI')
                || str_starts_with((string) getenv('TERM'), 'xterm');
        }

        return function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }
}
