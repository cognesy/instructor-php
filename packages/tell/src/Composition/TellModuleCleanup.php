<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition;

use Throwable;

/** @internal */
final readonly class TellModuleCleanup
{
    /**
     * @param list<array{id: string, instance: object}> $constructed
     * @return list<string>
     */
    public static function dispose(array $constructed): array
    {
        $errors = [];
        foreach (array_reverse($constructed) as $module) {
            if (! $module['instance'] instanceof CanDisposeTellModule) {
                continue;
            }
            try {
                $module['instance']->dispose();
            } catch (Throwable $error) {
                $errors[] = $module['id'].' ('.get_debug_type($error).')';
            }
        }

        return $errors;
    }
}
