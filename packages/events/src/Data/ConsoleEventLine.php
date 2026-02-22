<?php declare(strict_types=1);

namespace Cognesy\Events\Data;

use Cognesy\Events\Enums\ConsoleColor;

readonly final class ConsoleEventLine
{
    public function __construct(
        public string $label,
        public string $message,
        public ConsoleColor $color = ConsoleColor::Default,
        public ?string $context = null,
    ) {}
}
