<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\ClaudeCodeCli\Infrastructure\Execution;

enum SandboxDriver: string
{
    case Host = 'host';
    case Docker = 'docker';
    case Podman = 'podman';
    case Firejail = 'firejail';
    case Bubblewrap = 'bubblewrap';
}
