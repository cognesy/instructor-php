<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Gemini\Domain\Enum;

/**
 * Output format for Gemini CLI (maps to --output-format flag)
 */
enum OutputFormat: string
{
    case Text = 'text';
    case Json = 'json';
    case StreamJson = 'stream-json';
}
