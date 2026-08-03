<?php declare(strict_types=1);

namespace Cognesy\Agents\Exceptions;

use Cognesy\Agents\Hook\Enums\HookTrigger;
use RuntimeException;

final class HookContractViolationException extends RuntimeException
{
    public function __construct(
        public readonly HookTrigger $trigger,
        public readonly ?string $hookName,
        public readonly string $field,
    ) {
        $hook = $hookName ?? 'anonymous';
        parent::__construct("Hook '{$hook}' cannot mutate '{$field}' on trigger '{$trigger->value}'.");
    }
}
