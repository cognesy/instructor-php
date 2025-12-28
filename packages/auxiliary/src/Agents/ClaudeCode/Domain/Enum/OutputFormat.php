<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\ClaudeCode\Domain\Enum;

enum OutputFormat: string
{
    case Text = 'text';
    case Json = 'json';
    case StreamJson = 'stream-json';
}
