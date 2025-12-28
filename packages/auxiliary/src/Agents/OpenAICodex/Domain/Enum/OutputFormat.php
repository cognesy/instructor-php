<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\OpenAICodex\Domain\Enum;

/**
 * Output format for Codex CLI
 */
enum OutputFormat: string
{
    case Text = 'text';    // Default formatted text output
    case Json = 'json';    // JSONL streaming (--json flag)
}
