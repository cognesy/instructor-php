<?php declare(strict_types=1);

namespace Cognesy\Telemetry\Tests\Integration\Support;

use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\Common\Execution\CliBinaryGuard;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\SandboxMode;
use Cognesy\Config\Env;
use PHPUnit\Framework\SkippedWithMessageException;
use Throwable;

final class InteropEnv
{
    private static ?bool $codexUsable = null;
    private static ?string $codexSkipReason = null;

    public static function isEnabled(): bool
    {
        return match (strtolower((string) Env::get('TELEMETRY_INTEROP_ENABLED', ''))) {
            '1', 'true', 'yes', 'on' => true,
            default => false,
        };
    }

    public static function requireInteropEnabled(): void
    {
        if (self::isEnabled()) {
            return;
        }

        throw new SkippedWithMessageException(
            'Set TELEMETRY_INTEROP_ENABLED=1 to run live telemetry backend interop tests.',
        );
    }

    public static function requireLogfire(): void
    {
        self::requireInteropEnabled();
        self::requireVars(['LOGFIRE_TOKEN', 'LOGFIRE_OTLP_ENDPOINT', 'LOGFIRE_READ_TOKEN']);
    }

    public static function requireLangfuse(): void
    {
        self::requireInteropEnabled();
        self::requireVars(['LANGFUSE_BASE_URL', 'LANGFUSE_PUBLIC_KEY', 'LANGFUSE_SECRET_KEY']);
    }

    public static function requireOpenAi(): void
    {
        self::requireVars(['OPENAI_API_KEY']);
    }

    public static function requireCodexBinary(): void
    {
        self::requireInteropEnabled();

        if (self::codexUsable()) {
            return;
        }

        throw new SkippedWithMessageException(self::$codexSkipReason ?? 'Codex CLI is not usable.');
    }

    /** @param list<string> $vars */
    private static function requireVars(array $vars): void
    {
        $missing = array_values(array_filter(
            $vars,
            static fn(string $name): bool => trim((string) Env::get($name, '')) === '',
        ));

        if ($missing === []) {
            return;
        }

        throw new SkippedWithMessageException(
            'Missing required interop env vars: ' . implode(', ', $missing),
        );
    }

    private static function codexUsable(): bool
    {
        if (self::$codexUsable !== null) {
            return self::$codexUsable;
        }

        if (!CliBinaryGuard::isAvailable('codex')) {
            self::$codexSkipReason = 'codex binary not found in PATH.';
            self::$codexUsable = false;

            return self::$codexUsable;
        }

        try {
            $response = AgentCtrl::codex()
                ->withTimeout(20)
                ->withSandbox(SandboxMode::ReadOnly)
                ->inDirectory(getcwd() ?: '.')
                ->execute('Reply with exactly: OK');
        } catch (Throwable $e) {
            self::$codexSkipReason = 'Codex CLI is installed but not usable for interop tests: '
                . $e->getMessage();
            self::$codexUsable = false;

            return self::$codexUsable;
        }

        if ($response->isSuccess()) {
            self::$codexUsable = true;

            return self::$codexUsable;
        }

        $snippet = trim(substr($response->text(), 0, 160));
        self::$codexSkipReason = match ($snippet !== '') {
            true => 'Codex CLI is installed but not usable for interop tests: ' . $snippet,
            default => 'Codex CLI is installed but exited with code '
                . $response->exitCode
                . ' during interop preflight.',
        };
        self::$codexUsable = false;

        return self::$codexUsable;
    }
}
