<?php declare(strict_types=1);

namespace Examples\Support;

use Cognesy\Http\Creation\HttpClientDefaults;
use Cognesy\Http\Extras\Middleware\RecordReplay\RecordReplayMiddleware;

/**
 * Boot hook for the `./examples/` real ↔ pre-recorded HTTP switch.
 *
 * Every example's run.php requires examples/boot.php, which calls
 * {@see self::bootFromEnv()}. Based on two env vars it (optionally) registers a
 * RecordReplayMiddleware as ambient HTTP middleware, so every implicitly-built
 * client in the example records or replays — without editing the example.
 * Examples that construct their own HTTP client are untouched (the ambient hook
 * only applies on the implicit-build path; see HttpClientDefaults).
 *
 *   INSTRUCTOR_EXAMPLES_HTTP            = pass | record | replay   (default: pass)
 *   INSTRUCTOR_EXAMPLES_RECORDINGS_DIR = storage dir              (default: tmp/examples-recordings)
 *
 * Default is `pass` → zero behavior change. `replay` is hermetic (no network) and
 * provisions dummy provider keys so a keyless CI lane works.
 */
final class HttpRecordingBoot
{
    public const ENV_MODE = 'INSTRUCTOR_EXAMPLES_HTTP';
    public const ENV_DIR = 'INSTRUCTOR_EXAMPLES_RECORDINGS_DIR';

    /**
     * Provider key env vars set to a dummy value in replay mode when unset, so
     * config resolution succeeds on a keyless runner. The value is never used —
     * replay serves recordings and never authenticates.
     */
    private const PROVIDER_KEY_ENV = [
        'OPENAI_API_KEY', 'ANTHROPIC_API_KEY', 'GEMINI_API_KEY', 'COHERE_API_KEY',
        'MISTRAL_API_KEY', 'GROQ_API_KEY', 'OPENROUTER_API_KEY', 'DEEPSEEK_API_KEY',
        'XAI_API_KEY', 'TOGETHER_API_KEY', 'FIREWORKS_API_KEY', 'CEREBRAS_API_KEY',
        'MOONSHOT_API_KEY', 'PERPLEXITY_API_KEY', 'SAMBANOVA_API_KEY', 'JINA_API_KEY',
        'HUGGINGFACE_API_KEY', 'MINIMAXI_API_KEY', 'AZURE_OPENAI_API_KEY',
    ];

    public static function bootFromEnv(): void {
        $mode = self::env(self::ENV_MODE) ?? RecordReplayMiddleware::MODE_PASS;
        $root = self::env(self::ENV_DIR) ?? (getcwd() . '/tmp/examples-recordings');
        // Per-example namespace: each example's recordings live under their own
        // subdir, so a refresh is scoped to one folder and cross-example collisions
        // are impossible even for identical requests.
        self::configure($mode, self::withExampleNamespace($root));
    }

    /**
     * Append a per-example namespace derived from the running run.php path
     * (…/examples/<Group>/<Example>/run.php → <root>/<Group>/<Example>). Returns
     * $root unchanged when the script is not under an examples/ tree.
     */
    public static function withExampleNamespace(string $root, ?string $scriptPath = null): string {
        $scriptPath = $scriptPath ?? ($_SERVER['SCRIPT_FILENAME'] ?? '');
        if ($scriptPath === '') {
            return $root;
        }
        // Normalize to an absolute path so the namespace is identical whether the
        // example was launched by the hub (absolute path) or directly with a
        // relative path — otherwise record and replay would land in different dirs.
        $resolved = realpath($scriptPath);
        if ($resolved === false) {
            $resolved = str_starts_with($scriptPath, '/') || str_starts_with($scriptPath, '\\')
                ? $scriptPath
                : (getcwd() . '/' . $scriptPath);
        }
        $norm = str_replace('\\', '/', $resolved);
        $pos = strrpos($norm, '/examples/');
        if ($pos === false) {
            return $root;
        }
        $rel = dirname(substr($norm, $pos + strlen('/examples/')));
        if ($rel === '' || $rel === '.') {
            return $root;
        }
        return rtrim($root, '/') . '/' . $rel;
    }

    /**
     * Register (or skip) the record/replay middleware for the given mode.
     * @return bool true if middleware was attached, false for pass-through.
     */
    public static function configure(string $mode, string $dir): bool {
        if ($mode === RecordReplayMiddleware::MODE_PASS) {
            return false;
        }
        if (!in_array($mode, [RecordReplayMiddleware::MODE_RECORD, RecordReplayMiddleware::MODE_REPLAY], true)) {
            throw new \InvalidArgumentException(
                self::ENV_MODE . " must be one of pass|record|replay, got: {$mode}",
            );
        }

        $isReplay = $mode === RecordReplayMiddleware::MODE_REPLAY;
        if ($isReplay) {
            self::provisionDummyKeys();
        }

        HttpClientDefaults::withMiddleware(new RecordReplayMiddleware(
            mode: $mode,
            storageDir: $dir,
            fallbackToRealRequests: !$isReplay, // replay is hermetic
        ));

        return true;
    }

    private static function provisionDummyKeys(): void {
        foreach (self::PROVIDER_KEY_ENV as $key) {
            if (self::env($key) === null) {
                putenv("{$key}=dummy-replay-key");
                $_ENV[$key] = 'dummy-replay-key';
                $_SERVER[$key] = 'dummy-replay-key';
            }
        }
    }

    private static function env(string $key): ?string {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }
        foreach ([$_ENV[$key] ?? null, $_SERVER[$key] ?? null] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }
        return null;
    }
}
