<?php declare(strict_types=1);

namespace Cognesy\Utils\Sandbox\Config;

final class ExecutionPolicy
{
    private readonly string $baseDir;
    private readonly int $timeoutSeconds;
    private readonly ?int $idleTimeoutSeconds;
    private readonly string $memoryLimit;
    private readonly array $readablePaths;
    private readonly array $writablePaths;
    private readonly array $env;
    private readonly bool $inheritEnv;
    private readonly bool $networkEnabled;
    private readonly int $stdoutLimitBytes;
    private readonly int $stderrLimitBytes;

    private function __construct(
        string $baseDir = '/tmp',
        int $timeoutSeconds = 5,
        ?int $idleTimeoutSeconds = null,
        string $memoryLimit = '128M',
        array $readablePaths = [],
        array $writablePaths = [],
        array $env = [],
        bool $inheritEnv = false,
        bool $networkEnabled = false,
        int $stdoutLimitBytes = 1048576,
        int $stderrLimitBytes = 1048576,
    ) {
        $this->baseDir = $baseDir;
        $this->timeoutSeconds = max(1, $timeoutSeconds);
        $this->idleTimeoutSeconds = $idleTimeoutSeconds !== null ? max(1, $idleTimeoutSeconds) : null;
        $this->memoryLimit = self::normalizeMemoryLimit($memoryLimit);
        $this->readablePaths = $readablePaths;
        $this->writablePaths = $writablePaths;
        $this->env = $env;
        $this->inheritEnv = $inheritEnv;
        $this->networkEnabled = $networkEnabled;
        $this->stdoutLimitBytes = max(1024, $stdoutLimitBytes);
        $this->stderrLimitBytes = max(1024, $stderrLimitBytes);
    }

    public static function in(string $baseDir): self {
        return new self(baseDir: $baseDir);
    }

    public static function default(): self {
        return new self();
    }

    // Accessors //////////////////////////////////////////////////////////////

    public function baseDir(): string {
        return $this->baseDir;
    }

    public function timeoutSeconds(): int {
        return $this->timeoutSeconds;
    }

    public function idleTimeoutSeconds(): ?int {
        return $this->idleTimeoutSeconds;
    }

    public function memoryLimit(): string {
        return $this->memoryLimit;
    }

    public function readablePaths(): array {
        return $this->readablePaths;
    }

    public function writablePaths(): array {
        return $this->writablePaths;
    }

    public function env(): array {
        return $this->env;
    }

    public function inheritEnv(): bool {
        return $this->inheritEnv;
    }

    public function networkEnabled(): bool {
        return $this->networkEnabled;
    }

    public function stdoutLimitBytes(): int {
        return $this->stdoutLimitBytes;
    }

    public function stderrLimitBytes(): int {
        return $this->stderrLimitBytes;
    }

    // Mutators (immutable) //////////////////////////////////////////////////

    public function withTimeout(int $seconds): self {
        return $this->with(timeoutSeconds: $seconds);
    }

    public function withIdleTimeout(?int $seconds): self {
        return $this->with(idleTimeoutSeconds: $seconds);
    }

    public function withMemory(string $limit): self {
        return $this->with(memoryLimit: self::normalizeMemoryLimit($limit));
    }

    public function withReadablePaths(string ...$paths): self {
        return $this->with(readablePaths: $paths);
    }

    public function withWritablePaths(string ...$paths): self {
        return $this->with(writablePaths: $paths);
    }

    public function withEnv(array $env, bool $inherit = false): self {
        return $this->with(env: $env, inheritEnv: $inherit);
    }

    public function inheritEnvironment(bool $inherit = true): self {
        return $this->with(inheritEnv: $inherit);
    }

    public function withNetwork(bool $enabled): self {
        return $this->with(networkEnabled: $enabled);
    }

    public function withOutputCaps(int $stdoutBytes, int $stderrBytes): self {
        return $this->with(stdoutLimitBytes: $stdoutBytes, stderrLimitBytes: $stderrBytes);
    }

    // Internal //////////////////////////////////////////////////////////////

    public function with(
        ?string $baseDir = null,
        ?int $timeoutSeconds = null,
        ?int $idleTimeoutSeconds = null,
        ?string $memoryLimit = null,
        ?array $readablePaths = null,
        ?array $writablePaths = null,
        ?array $env = null,
        ?bool $inheritEnv = null,
        ?bool $networkEnabled = null,
        ?int $stdoutLimitBytes = null,
        ?int $stderrLimitBytes = null,
    ): self {
        return new self(
            baseDir: $baseDir ?? $this->baseDir,
            timeoutSeconds: $timeoutSeconds ?? $this->timeoutSeconds,
            idleTimeoutSeconds: $idleTimeoutSeconds ?? $this->idleTimeoutSeconds,
            memoryLimit: $memoryLimit ?? $this->memoryLimit,
            readablePaths: $readablePaths ?? $this->readablePaths,
            writablePaths: $writablePaths ?? $this->writablePaths,
            env: $env ?? $this->env,
            inheritEnv: $inheritEnv ?? $this->inheritEnv,
            networkEnabled: $networkEnabled ?? $this->networkEnabled,
            stdoutLimitBytes: $stdoutLimitBytes ?? $this->stdoutLimitBytes,
            stderrLimitBytes: $stderrLimitBytes ?? $this->stderrLimitBytes,
        );
    }

    private static function normalizeMemoryLimit(string $limit): string {
        $limit = strtoupper(trim($limit));
        if ($limit === '-1') {
            throw new \InvalidArgumentException('Unbounded memory limit (-1) is not allowed');
        }
        if (!preg_match('/^\d+([KMG])?$/', $limit)) {
            throw new \InvalidArgumentException('Invalid memory limit format');
        }
        $value = (int)$limit;
        $unit = self::unitOf($limit);
        $bytes = match ($unit) {
            'G' => $value * 1024 * 1024 * 1024,
            'M' => $value * 1024 * 1024,
            'K' => $value * 1024,
            default => $value,
        };
        $max = 1024 * 1024 * 1024; // 1G clamp
        if ($bytes > $max) {
            $bytes = $max;
        }
        $mb = (int)max(1, intdiv($bytes, 1024 * 1024));
        return $mb . 'M';
    }

    private static function unitOf(string $limit): string {
        return match (true) {
            str_ends_with($limit, 'G') => 'G',
            str_ends_with($limit, 'M') => 'M',
            str_ends_with($limit, 'K') => 'K',
            default => '',
        };
    }
}
