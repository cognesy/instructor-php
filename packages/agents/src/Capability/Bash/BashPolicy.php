<?php

declare(strict_types=1);

namespace Cognesy\Agents\Capability\Bash;

class BashPolicy
{
    public function __construct(
        public int $maxOutputChars = 32 * 1024,
        public int $headChars = 8 * 1024,
        public int $tailChars = 24 * 1024,
        public int $timeout = 120,
        public int $stdoutLimitBytes = 128 * 1024,
        public int $stderrLimitBytes = 64 * 1024,
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
