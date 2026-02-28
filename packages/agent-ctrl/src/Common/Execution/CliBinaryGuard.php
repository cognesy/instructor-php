<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Common\Execution;

use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\Sandbox\Enums\SandboxDriver;
use RuntimeException;

final class CliBinaryGuard
{
    public static function assertAvailableForDriver(string $binary, AgentType $agentType, SandboxDriver $driver): void
    {
        if (!self::requiresHostBinaryPreflight($driver)) {
            return;
        }

        self::assertAvailable($binary, $agentType);
    }

    public static function assertAvailable(string $binary, AgentType $agentType): void
    {
        if (self::isAvailable($binary)) {
            return;
        }

        throw new RuntimeException(self::missingBinaryMessage($binary, $agentType));
    }

    public static function isAvailable(string $binary, ?string $path = null): bool
    {
        if ($binary === '') {
            return false;
        }

        if (self::isPathLike($binary)) {
            return self::isExecutableFile($binary);
        }

        $pathValue = $path ?? getenv('PATH');
        if (!is_string($pathValue) || $pathValue === '') {
            return false;
        }

        foreach (explode(PATH_SEPARATOR, $pathValue) as $directory) {
            if ($directory === '') {
                continue;
            }

            foreach (self::candidateBinaryNames($binary) as $name) {
                $candidate = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
                if (self::isExecutableFile($candidate)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function missingBinaryMessage(string $binary, AgentType $agentType): string
    {
        $label = match ($agentType) {
            AgentType::ClaudeCode => 'Claude Code CLI',
            AgentType::Codex => 'Codex CLI',
            AgentType::OpenCode => 'OpenCode CLI',
        };

        $hint = match ($agentType) {
            AgentType::ClaudeCode => 'Install Claude Code CLI and ensure `claude` is available in PATH.',
            AgentType::Codex => 'Install via `npm install -g @openai/codex` and ensure `codex` is available in PATH.',
            AgentType::OpenCode => 'Install via `npm install -g opencode` (or `curl -fsSL https://get.opencode.dev | bash`) and ensure `opencode` is available in PATH.',
        };

        return "{$label} executable `{$binary}` was not found in PATH. {$hint}";
    }

    private static function isPathLike(string $binary): bool
    {
        if (str_contains($binary, '/')) {
            return true;
        }

        return str_contains($binary, DIRECTORY_SEPARATOR);
    }

    private static function isExecutableFile(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }

        if (DIRECTORY_SEPARATOR !== '\\') {
            return is_executable($path);
        }

        $lowerPath = strtolower($path);
        foreach (self::candidateExtensions() as $extension) {
            if ($extension === '') {
                continue;
            }
            if (str_ends_with($lowerPath, strtolower($extension))) {
                return true;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private static function candidateBinaryNames(string $binary): array
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            return [$binary];
        }

        $names = [$binary];
        $lowerBinary = strtolower($binary);

        foreach (self::candidateExtensions() as $extension) {
            if ($extension === '') {
                continue;
            }
            if (str_ends_with($lowerBinary, strtolower($extension))) {
                continue;
            }
            $names[] = $binary . $extension;
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private static function candidateExtensions(): array
    {
        $pathExt = getenv('PATHEXT');
        if (!is_string($pathExt) || $pathExt === '') {
            return ['.exe', '.bat', '.cmd'];
        }

        $extensions = array_filter(array_map('trim', explode(';', $pathExt)));
        if ($extensions === []) {
            return ['.exe', '.bat', '.cmd'];
        }

        return array_values($extensions);
    }

    private static function requiresHostBinaryPreflight(SandboxDriver $driver): bool
    {
        return match ($driver) {
            SandboxDriver::Host,
            SandboxDriver::Firejail,
            SandboxDriver::Bubblewrap => true,
            SandboxDriver::Docker,
            SandboxDriver::Podman => false,
        };
    }
}
