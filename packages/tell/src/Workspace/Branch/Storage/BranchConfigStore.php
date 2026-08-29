<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Branch\Storage;

use Cognesy\Tell\Workspace\Branch\BranchName;
use Cognesy\Tell\Workspace\Filesystem\PrivateFilesystem;
use Cognesy\Tell\Workspace\WorkspaceException;
use Cognesy\Tell\Workspace\WorkspaceState;
use InvalidArgumentException;
use JsonException;

/** Secret-free, versioned branch-local runtime intent. */
final readonly class BranchConfigStore
{
    private const int SCHEMA_VERSION = 1;
    private const int MAX_TOOL_NAMES = 50;

    /** @var array<string, array{type: 'enum'|'int'|'list'|'string', min?: int, max?: int, values?: list<string>}> */
    private const array KEYS = [
        'connection' => ['type' => 'string'],
        'model' => ['type' => 'string'],
        'reasoningEffort' => ['type' => 'enum', 'values' => ['minimal', 'low', 'medium', 'high', 'xhigh', 'max']],
        'output' => ['type' => 'enum', 'values' => ['toon', 'text', 'human', 'json', 'events']],
        'tools' => ['type' => 'list'],
        'maxRetries' => ['type' => 'int', 'min' => 0, 'max' => 10],
        'timeoutMs' => ['type' => 'int', 'min' => 1, 'max' => 3_600_000],
        'maxOutputChars' => ['type' => 'int', 'min' => 1, 'max' => 1_000_000],
        'maxToolOutputChars' => ['type' => 'int', 'min' => 1, 'max' => 250_000],
        'maxToolCalls' => ['type' => 'int', 'min' => 0, 'max' => 1_000],
        'maxSpillBytes' => ['type' => 'int', 'min' => 0, 'max' => 5_000_000],
        'maxStubBytes' => ['type' => 'int', 'min' => 0, 'max' => 100_000],
    ];

    /** @var array<string, int|list<string>|string> */
    private const array DEFAULTS = [
        'connection' => 'openai',
        'output' => 'human',
        'tools' => [],
        'maxRetries' => 0,
        'timeoutMs' => 30_000,
        'maxOutputChars' => 200_000,
        'maxToolOutputChars' => 40_000,
        'maxToolCalls' => 100,
        'maxSpillBytes' => 200_000,
        'maxStubBytes' => 2_000,
    ];

    private PrivateFilesystem $files;

    public function __construct(private WorkspaceState $workspace, ?PrivateFilesystem $files = null) {
        $this->files = $files ?? PrivateFilesystem::forWorkspace();
    }

    /** @return array{version: int, values: array<string, mixed>} */
    public function read(string $branch): array {
        $path = $this->path($branch);
        if (!$this->files->exists($path)) {
            return ['version' => 0, 'values' => []];
        }
        $bytes = $this->files->read($path, 'branch config');
        try {
            $data = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new WorkspaceException('Tell branch config is invalid JSON.', previous: $error);
        }
        if (
            !is_array($data)
            || array_is_list($data)
            || array_keys($data) !== ['schema', 'version', 'values']
            || $data['schema'] !== self::SCHEMA_VERSION
            || !is_int($data['version'])
            || $data['version'] < 1
            || !is_array($data['values'])
            || array_is_list($data['values'])
        ) {
            throw new WorkspaceException('Tell branch config has an unsupported shape.');
        }
        foreach ($data['values'] as $key => $value) {
            $this->assertValue($key, $value);
        }

        return ['version' => $data['version'], 'values' => $data['values']];
    }

    /** @return array<string, int|list<string>|string> */
    public function runtimeValues(string $branch): array {
        $values = $this->read($branch)['values'];

        return $values;
    }

    /** @return list<string> */
    public function keys(): array {
        return array_keys(self::KEYS);
    }

    /**
     * @return array{version: int, values: array<string, mixed>, provenance: array<string, 'branch'|'bundled'>}
     */
    public function effective(string $branch): array {
        $config = $this->read($branch);
        $values = self::DEFAULTS;
        $provenance = array_fill_keys(array_keys(self::DEFAULTS), 'bundled');
        foreach ($config['values'] as $key => $value) {
            $values[$key] = $value;
            $provenance[$key] = 'branch';
        }

        return [
            'version' => $config['version'],
            'values' => $values,
            'provenance' => $provenance,
        ];
    }

    /** @return array{version: int, values: array<string, mixed>} */
    public function set(string $branch, string $key, mixed $value, int $expectedVersion): array {
        $result = $this->mutate($branch, $expectedVersion, function (array $values) use ($key, $value): array {
            $this->assertValue($key, $value);
            $values[$key] = $value;
            ksort($values, SORT_STRING);

            return $values;
        });

        return ['version' => $result['version'], 'values' => $result['values']];
    }

    /** @return array{version: int, values: array<string, mixed>} */
    public function delete(string $branch, string $key, int $expectedVersion): array {
        $result = $this->mutate($branch, $expectedVersion, function (array $values) use ($key): array {
            $this->assertKey($key);
            unset($values[$key]);

            return $values;
        });

        return ['version' => $result['version'], 'values' => $result['values']];
    }

    public function inherit(string $source, string $destination): void {
        $config = $this->read($source);
        if ($config['version'] === 0) {
            return;
        }
        $this->mutate($destination, 0, static fn (array $values): array => $config['values']);
    }

    /** @return array{version: int, values: array<string, mixed>} */
    /** @param callable(array<string, mixed>): array<string, mixed> $operation */
    private function mutate(string $branch, int $expectedVersion, callable $operation): array {
        if ($expectedVersion < 0) {
            throw new InvalidArgumentException('--if-version must be a non-negative integer.');
        }

        return $this->files->withExclusiveLock($this->lockPath($branch), 'branch config lock', function () use ($branch, $expectedVersion, $operation): array {
            $current = $this->read($branch);
            if ($current['version'] !== $expectedVersion) {
                throw new WorkspaceException('Tell branch config version conflict. Re-read and retry.');
            }

            $values = $operation($current['values']);

            return $this->write($branch, $values, $current['version']);
        });
    }

    /** @param array<string, mixed> $values @return array{version: int, values: array<string, mixed>} */
    private function write(string $branch, array $values, int $previousVersion): array {
        $path = $this->path($branch);
        $version = $previousVersion + 1;
        try {
            $bytes = json_encode([
                'schema' => self::SCHEMA_VERSION,
                'version' => $version,
                'values' => $values,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
        } catch (JsonException $error) {
            throw new WorkspaceException('Tell branch config could not be encoded.', previous: $error);
        }
        $this->files->writeAtomically($path, $bytes, 'branch config', true);

        return ['version' => $version, 'values' => $values];
    }

    private function path(string $branch): string {
        $name = $branch === 'main' ? 'main' : BranchName::fromStored($branch)->toString();

        return $this->workspace->paths->config . '/' . $name . '.json';
    }

    private function lockPath(string $branch): string {
        return $this->path($branch) . '.lock';
    }

    private function assertKey(mixed $key): void {
        if (!is_string($key) || !array_key_exists($key, self::KEYS)) {
            $display = is_string($key) ? $key : get_debug_type($key);
            throw new InvalidArgumentException("Unknown Tell branch config key: {$display}");
        }
    }

    private function assertValue(mixed $key, mixed $value): void {
        $this->assertKey($key);
        $spec = self::KEYS[$key];
        if ($spec['type'] === 'string') {
            if (!is_string($value) || trim($value) === '' || $this->containsSecret($value)) {
                throw new InvalidArgumentException("Tell branch config {$key} must be a non-secret non-empty string.");
            }

            return;
        }
        if ($spec['type'] === 'int') {
            if (!is_int($value)
                || $value < ($spec['min'] ?? 0)
                || $value > ($spec['max'] ?? PHP_INT_MAX)) {
                throw new InvalidArgumentException("Tell branch config {$key} has an invalid range.");
            }

            return;
        }
        if ($spec['type'] === 'enum') {
            if (!is_string($value) || !in_array($value, $spec['values'] ?? [], true)) {
                $allowed = implode(', ', $spec['values'] ?? []);
                throw new InvalidArgumentException("Tell branch config {$key} must be one of: {$allowed}.");
            }

            return;
        }
        if (!is_array($value) || !array_is_list($value) || count($value) > self::MAX_TOOL_NAMES) {
            throw new InvalidArgumentException("Tell branch config {$key} must be a bounded list of non-empty strings.");
        }
        foreach ($value as $item) {
            if (!is_string($item) || trim($item) === '' || $this->containsSecret($item)) {
                throw new InvalidArgumentException("Tell branch config {$key} must be a bounded list of non-secret non-empty strings.");
            }
        }
    }

    private function containsSecret(string $value): bool {
        return preg_match('/(?:api[_-]?key|token|password|secret|authorization|bearer\s+|:\/\/[^\/\s:]+:[^@\s]+@)/i', $value) === 1;
    }
}
