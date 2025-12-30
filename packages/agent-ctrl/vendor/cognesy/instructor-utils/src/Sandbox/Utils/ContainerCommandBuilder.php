<?php declare(strict_types=1);

namespace Cognesy\Utils\Sandbox\Utils;

use Cognesy\Utils\Sandbox\Data\Mount;

final class ContainerCommandBuilder
{
    private string $runtimeBin;
    private string $image = '';
    private bool $networkEnabled = false;
    private int $pidsLimit = 20;
    private string $memory = '128M';
    private string $cpus = '0.5';
    private bool $enableResourceLimits = true;
    private bool $readOnlyRoot = true;
    private string $tmpfs = '/tmp:rw,noexec,nodev,nosuid,size=64m';
    private bool $noNewPrivileges = true;
    private string $user = '65534:65534';
    private string $containerWorkdir = '/work';
    private ?string $hostWorkdir = null;
    /** @var list<Mount> */
    private array $roMounts = [];
    /** @var list<Mount> */
    private array $rwMounts = [];
    /** @var array<string,string> */
    private array $env = [];
    /** @var list<string> */
    private array $innerArgv = [];
    /** @var list<string> */
    private array $globalFlags = [];

    private function __construct(string $runtimeBin) {
        $this->runtimeBin = $runtimeBin;
    }

    public static function docker(string $bin = 'docker'): self {
        return new self($bin);
    }

    public static function podman(string $bin = 'podman'): self {
        return new self($bin);
    }

    public function withImage(string $image): self {
        $this->image = $image;
        return $this;
    }

    public function withNetwork(bool $enabled): self {
        $this->networkEnabled = $enabled;
        return $this;
    }

    public function withPidsLimit(int $limit): self {
        $this->pidsLimit = max(1, $limit);
        return $this;
    }

    public function withMemory(string $limit): self {
        $this->memory = $limit;
        return $this;
    }

    public function withCpus(string $cpus): self {
        $this->cpus = $cpus;
        return $this;
    }

    public function withReadOnlyRoot(bool $ro = true): self {
        $this->readOnlyRoot = $ro;
        return $this;
    }

    public function withTmpfs(string $spec): self {
        $this->tmpfs = $spec;
        return $this;
    }

    public function withNoNewPrivileges(bool $nn = true): self {
        $this->noNewPrivileges = $nn;
        return $this;
    }

    public function withUser(string $user): self {
        $this->user = $user;
        return $this;
    }

    public function withWorkdir(string $containerPath): self {
        $this->containerWorkdir = $containerPath;
        return $this;
    }

    public function mountWorkdir(string $hostPath): self {
        $this->hostWorkdir = $hostPath;
        return $this;
    }

    public function addReadonlyMount(string $hostPath, string $containerPath): self {
        $this->roMounts[] = new Mount($hostPath, $containerPath, 'ro,bind');
        return $this;
    }

    public function addWritableMount(string $hostPath, string $containerPath): self {
        $this->rwMounts[] = new Mount($hostPath, $containerPath, 'rw,bind');
        return $this;
    }

    /** @param array<string,string> $env */
    public function withEnv(array $env): self {
        $this->env = $env;
        return $this;
    }

    /** @param list<string> $argv */
    public function withInnerArgv(array $argv): self {
        $this->innerArgv = $argv;
        return $this;
    }

    /** @param list<string> $flags */
    public function withGlobalFlags(array $flags): self {
        $this->globalFlags = $flags;
        return $this;
    }

    public function addGlobalFlag(string $flag): self {
        $this->globalFlags[] = $flag;
        return $this;
    }

    public function withResourceLimits(bool $enabled): self {
        $this->enableResourceLimits = $enabled;
        return $this;
    }

    /** @return list<string> */
    public function build(): array {
        $cmd = [$this->runtimeBin];
        $cmd = [...$cmd, ...$this->globalFlags];
        $cmd = [...$cmd, 'run', '--rm'];
        if (!$this->networkEnabled) {
            $cmd[] = '--network=none';
        }
        $cmd = [...$cmd, '--pids-limit=' . (string)$this->pidsLimit];
        if ($this->enableResourceLimits) {
            $cmd = [...$cmd, '--memory', $this->normalizeMemoryForRuntime($this->memory)];
            $cmd = [...$cmd, '--cpus', $this->cpus];
        }
        if ($this->readOnlyRoot) {
            $cmd[] = '--read-only';
        }
        $cmd = [...$cmd, '--tmpfs', $this->tmpfs];
        if ($this->noNewPrivileges) {
            $cmd = [...$cmd, '--cap-drop=ALL', '--security-opt', 'no-new-privileges'];
        }
        $cmd = [...$cmd, '-u', $this->user];
        if ($this->hostWorkdir !== null) {
            $cmd = [...$cmd, '-v', $this->hostWorkdir . ':' . $this->containerWorkdir . ':rw,bind'];
        }
        foreach ($this->rwMounts as $m) {
            $cmd = [...$cmd, '-v', $m->toVolumeArg()];
        }
        foreach ($this->roMounts as $m) {
            $cmd = [...$cmd, '-v', $m->toVolumeArg()];
        }
        foreach ($this->env as $k => $v) {
            $cmd = [...$cmd, '-e', $k . '=' . $v];
        }
        $cmd = [...$cmd, '-w', $this->containerWorkdir];
        $cmd[] = $this->image;
        foreach ($this->innerArgv as $part) {
            $cmd[] = $part;
        }
        return $cmd;
    }

    private function normalizeMemoryForRuntime(string $policyLimit): string {
        $v = strtoupper($policyLimit);
        if (str_ends_with($v, 'G')) {
            return (string)((int)$v) . 'g';
        }
        if (str_ends_with($v, 'M')) {
            return (string)((int)$v) . 'm';
        }
        if (str_ends_with($v, 'K')) {
            return (string)((int)$v) . 'k';
        }
        return (string)((int)$v) . 'b';
    }
}
