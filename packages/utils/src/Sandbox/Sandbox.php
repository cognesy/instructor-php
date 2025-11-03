<?php declare(strict_types=1);

namespace Cognesy\Utils\Sandbox;

use Cognesy\Utils\Sandbox\Config\ExecutionPolicy;
use Cognesy\Utils\Sandbox\Contracts\CanExecuteCommand;
use Cognesy\Utils\Sandbox\Drivers\DockerSandbox;
use Cognesy\Utils\Sandbox\Drivers\HostSandbox;
use Cognesy\Utils\Sandbox\Drivers\PodmanSandbox;
use Cognesy\Utils\Sandbox\Drivers\FirejailSandbox;
use Cognesy\Utils\Sandbox\Drivers\BubblewrapSandbox;

final readonly class Sandbox
{
    private ExecutionPolicy $policy;

    public function __construct(ExecutionPolicy $policy) {
        $this->policy = $policy;
    }

    public static function with(ExecutionPolicy $policy) : self {
        return new self($policy);
    }

    public function using(string $driver) : CanExecuteCommand {
        return match($driver) {
            'host' => new HostSandbox($this->policy),
            'docker' => new DockerSandbox($this->policy),
            'podman' => new PodmanSandbox($this->policy),
            'firejail' => new FirejailSandbox($this->policy),
            'bubblewrap' => new BubblewrapSandbox($this->policy),
        };
    }

    public static function host(
        ExecutionPolicy $policy
    ): CanExecuteCommand {
        return new HostSandbox($policy);
    }

    public static function docker(
        ExecutionPolicy $policy,
        ?string $image = null,
        ?string $dockerBin = null
    ): CanExecuteCommand {
        return new DockerSandbox($policy, $image ?? 'alpine:3', $dockerBin);
    }

    public static function podman(
        ExecutionPolicy $policy,
        ?string $image = null,
        ?string $podmanBin = null,
    ) : CanExecuteCommand {
        return new PodmanSandbox($policy, $image ?? 'alpine:3', $podmanBin);
    }

    public static function firejail(
        ExecutionPolicy $policy,
        ?string $firejailBin = null,
    ): CanExecuteCommand {
        return new FirejailSandbox($policy, $firejailBin);
    }

    public static function bubblewrap(
        ExecutionPolicy $policy,
        ?string $bubblewrapBin = null,
    ): CanExecuteCommand {
        return new BubblewrapSandbox($policy, $bubblewrapBin);
    }
}
