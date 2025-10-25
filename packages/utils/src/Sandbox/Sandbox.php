<?php declare(strict_types=1);

namespace Cognesy\Utils\Sandbox;
use Cognesy\Utils\Sandbox\Config\ExecutionPolicy;
use Cognesy\Utils\Sandbox\Contracts\CanExecuteCommand;
use Cognesy\Utils\Sandbox\Drivers\DockerSandbox;
use Cognesy\Utils\Sandbox\Drivers\HostSandbox;
use Cognesy\Utils\Sandbox\Drivers\PodmanSandbox;
use Cognesy\Utils\Sandbox\Drivers\FirejailSandbox;
use Cognesy\Utils\Sandbox\Drivers\BubblewrapSandbox;

final class Sandbox
{
    public static function host(
        ExecutionPolicy $policy
    ): CanExecuteCommand {
        return new HostSandbox($policy);
    }

    public static function docker(
        ExecutionPolicy $policy,
        string $image,
        ?string $dockerBin = null
    ): CanExecuteCommand {
        return new DockerSandbox($policy, $image, $dockerBin);
    }

    public static function podman(
        ExecutionPolicy $policy,
        string $image,
        ?string $podmanBin = null,
    ) : CanExecuteCommand {
        return new PodmanSandbox($policy, $image, $podmanBin);
    }

    public static function firejail(
        ExecutionPolicy $policy,
        ?string $firejailBin = null,
    ): CanExecuteCommand {
        return new FirejailSandbox($policy, $firejailBin);
    }

    public static function bubblewrap(
        ExecutionPolicy $policy,
        ?string $bwrapBin = null,
    ): CanExecuteCommand {
        return new BubblewrapSandbox($policy, $bwrapBin);
    }
}
