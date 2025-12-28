<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\OpenCode\Domain\Enum;

/**
 * Output format for OpenCode CLI
 */
enum OutputFormat: string
{
    /** Formatted human-readable output */
    case Default = 'default';

    /** Raw nd-JSON events for programmatic processing */
    case Json = 'json';
}
