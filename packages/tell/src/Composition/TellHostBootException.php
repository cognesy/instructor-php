<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition;

use RuntimeException;
use Throwable;

final class TellHostBootException extends RuntimeException
{
    /** @param list<string> $cleanupErrors */
    public function __construct(
        public readonly string $module,
        public readonly array $cleanupErrors,
        Throwable $previous,
    ) {
        $cleanup = $cleanupErrors === [] ? '' : ' Cleanup failures: ' . implode('; ', $cleanupErrors);
        parent::__construct("Tell module {$module} failed to boot (" . get_debug_type($previous) . ").{$cleanup}", previous: $previous);
    }
}
