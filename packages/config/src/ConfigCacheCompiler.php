<?php declare(strict_types=1);

namespace Cognesy\Config;

use RuntimeException;

final readonly class ConfigCacheCompiler
{
    /**
     * @param array<string, mixed> $config
     * @param array<string, scalar|null> $env
     */
    public function compile(
        string $cachePath,
        ConfigFileSet $fileSet,
        array $config,
        array $env = [],
        int $schemaVersion = 1,
        ?string $generatedAt = null,
    ): void {
        $directory = dirname($cachePath);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $directory));
            }
        }

        $payload = [
            '_meta' => [
                'schema_version' => $schemaVersion,
                'files_hash' => $fileSet->filesHash(),
                'env_hash' => $this->envHash($env),
                'generated_at' => $generatedAt ?? gmdate(DATE_ATOM),
                'file_count' => $fileSet->count(),
                'files' => $fileSet->keys(),
            ],
            'config' => $config,
        ];

        $export = var_export($payload, true);
        $content = "<?php declare(strict_types=1);\n\nreturn {$export};\n";
        $tempPath = $cachePath . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $result = file_put_contents($tempPath, $content, LOCK_EX);
        if ($result === false) {
            throw new RuntimeException("Failed to write temporary config cache file: {$tempPath}");
        }

        if (!rename($tempPath, $cachePath)) {
            @unlink($tempPath);
            throw new RuntimeException("Failed to atomically replace config cache file: {$cachePath}");
        }
    }

    /** @param array<string, scalar|null> $env */
    private function envHash(array $env): string
    {
        ksort($env);

        return hash('sha256', json_encode($env, JSON_THROW_ON_ERROR));
    }
}
