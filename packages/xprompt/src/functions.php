<?php

declare(strict_types=1);

namespace Cognesy\Xprompt;

use Stringable;

/**
 * Recursively flatten a render tree to a string.
 *
 * Handles: null (skipped), string (pass-through), Prompt (rendered with ctx),
 * array (recurse + join), Stringable (cast), other (cast).
 */
function flatten(mixed $node, array $ctx = []): string
{
    return match (true) {
        $node === null          => '',
        is_string($node)        => $node,
        $node instanceof Prompt => $node->render(...$ctx),
        is_array($node)         => implode("\n\n", array_filter(
            array_map(fn(mixed $n): string => flatten($n, $ctx), $node),
            fn(string $s): bool => $s !== '',
        )),
        $node instanceof Stringable => (string) $node,
        default                 => (string) $node,
    };
}
