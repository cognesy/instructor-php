<?php declare(strict_types=1);

namespace Cognesy\Events\Enums;

enum ConsoleColor: string
{
    case Default = '0';
    case Red = '31';
    case Green = '32';
    case Yellow = '33';
    case Blue = '34';
    case Magenta = '35';
    case Cyan = '36';
    case Dark = '90';

    public function ansiCode(): string {
        return $this->value;
    }
}
