<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\Common\Enum;

enum SandboxDriver: string
{
    case Host = 'host';
    case Docker = 'docker';
    case Podman = 'podman';
    case Firejail = 'firejail';
    case Bubblewrap = 'bubblewrap';
}
