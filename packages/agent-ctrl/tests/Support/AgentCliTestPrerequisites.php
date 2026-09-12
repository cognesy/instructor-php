<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Tests\Support;

use Cognesy\AgentCtrl\Common\Execution\CliBinaryGuard;

final class AgentCliTestPrerequisites
{
    public static function ensureAvailable(string $binary, bool $hasAuth, string $authMessage): void
    {
        if (!CliBinaryGuard::isAvailable($binary)) {
            test()->markTestSkipped("{$binary} binary not found in PATH");
        }
        if (!$hasAuth) {
            test()->markTestSkipped($authMessage);
        }
    }
}
