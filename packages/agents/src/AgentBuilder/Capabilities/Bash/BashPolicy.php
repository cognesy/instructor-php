<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Bash;

class BashPolicy
{
    public function __construct(
        public int $maxOutputChars = 50000,
        public int $headChars = 8000,
        public int $tailChars = 40000,
        public int $timeout = 120,
        public int $stdoutLimitBytes = 5 * 1024 * 1024, // 5MB
        public int $stderrLimitBytes = 1 * 1024 * 1024, // 1MB
        public array $dangerousPatterns = [
            'rm -rf /',
            'mkfs',
            'shutdown',
            'reboot',
            'dd if=/dev/zero',
            '>:',
        ],
    ) {}
}
