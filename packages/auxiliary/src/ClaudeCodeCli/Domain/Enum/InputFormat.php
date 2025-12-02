<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\ClaudeCodeCli\Domain\Enum;

enum InputFormat: string
{
    case Text = 'text';
    case StreamJson = 'stream-json';
}
