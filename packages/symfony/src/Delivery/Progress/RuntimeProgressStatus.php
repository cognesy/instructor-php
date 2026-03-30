<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Symfony\Delivery\Progress;

use Cognesy\Events\Enums\ConsoleColor;

enum RuntimeProgressStatus: string
{
    case Started = 'started';
    case Progress = 'progress';
    case Stream = 'stream';
    case Completed = 'completed';
    case Failed = 'failed';

    public function color(): ConsoleColor
    {
        return match ($this) {
            self::Started => ConsoleColor::Blue,
            self::Progress => ConsoleColor::Cyan,
            self::Stream => ConsoleColor::Dark,
            self::Completed => ConsoleColor::Green,
            self::Failed => ConsoleColor::Red,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Started => 'RUN',
            self::Progress => 'STEP',
            self::Stream => 'STRM',
            self::Completed => 'DONE',
            self::Failed => 'FAIL',
        };
    }
}
