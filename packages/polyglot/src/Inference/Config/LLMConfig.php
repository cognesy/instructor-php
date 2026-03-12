<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Config;

use Cognesy\Config\BasePath;
use Cognesy\Config\Config;
use Cognesy\Config\Dsn;
use Cognesy\Polyglot\Inference\Data\InferencePricing;
use InvalidArgumentException;
use Throwable;

final class LLMConfig
{
    public const CONFIG_GROUP = 'llm';
    /** @var list<string> */
    private const INT_FIELDS = ['maxTokens', 'contextLength', 'maxOutputLength'];

    public static function group() : string {
        return self::CONFIG_GROUP;
    }

    public function __construct(
        public string $apiUrl = '',
        #[\SensitiveParameter]
        public string $apiKey = '',
        public string $endpoint = '',
        public array  $queryParams = [],
        public array  $metadata = [],
        public string $model = '',
        public int    $maxTokens = 1024,
        public int    $contextLength = 8000,
        public int    $maxOutputLength = 4096,
        public string $driver = 'openai-compatible',
        public array  $options = [],
        public array  $pricing = [],
    ) {
        $this->assertNoRetryPolicyInOptions($this->options);
    }

    private const PRESET_PATHS = [
        'config/llm/presets',
        'packages/polyglot/resources/config/llm/presets',
        'vendor/cognesy/instructor-php/packages/polyglot/resources/config/llm/presets',
        'vendor/cognesy/instructor-polyglot/resources/config/llm/presets',
    ];

    public static function fromPreset(string $preset, ?string $basePath = null): self {
        $basePaths = $basePath !== null ? [$basePath] : self::PRESET_PATHS;
        $resolvedPaths = BasePath::resolveExisting(...$basePaths);
        if ($resolvedPaths === []) {
            throw new InvalidArgumentException("No preset directory found for '{$preset}'. Searched: " . implode(', ', $basePaths));
        }
        $data = Config::fromPaths(...$resolvedPaths)
            ->load("{$preset}.yaml")
            ->toArray();
        return self::fromArray($data);
    }

    public static function fromArray(array $config) : LLMConfig {
        $typed = self::coerceScalarTypes($config);
        try {
            $instance = new self(...$typed);
        } catch (Throwable $e) {
            $data = json_encode($typed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            throw new InvalidArgumentException(
                message: "Invalid configuration for LLMConfig: {$e->getMessage()}\nData: {$data}",
                previous: $e,
            );
        }
        return $instance;
    }

    public static function fromDsn(string $dsn): self {
        return self::fromArray(Dsn::fromString($dsn)->toArray());
    }

    public function withOverrides(array $overrides) : self {
        $config = array_merge($this->toArray(), $overrides);
        return self::fromArray($config);
    }


    public function toArray() : array {
        return [
            'apiUrl' => $this->apiUrl,
            'apiKey' => $this->apiKey,
            'endpoint' => $this->endpoint,
            'queryParams' => $this->queryParams,
            'metadata' => $this->metadata,
            'model' => $this->model,
            'maxTokens' => $this->maxTokens,
            'contextLength' => $this->contextLength,
            'maxOutputLength' => $this->maxOutputLength,
            'driver' => $this->driver,
            'options' => $this->options,
            'pricing' => $this->pricing,
        ];
    }

    public function getPricing(): InferencePricing {
        return InferencePricing::fromArray($this->pricing);
    }

    private function assertNoRetryPolicyInOptions(array $options) : void {
        if (!array_key_exists('retryPolicy', $options) && !array_key_exists('retry_policy', $options)) {
            return;
        }

        throw new InvalidArgumentException('retryPolicy must be configured via an explicit retry policy, not LLM options.');
    }

    private static function coerceScalarTypes(array $config): array {
        $typed = $config;
        foreach (self::INT_FIELDS as $field) {
            if (!array_key_exists($field, $typed)) {
                continue;
            }
            $typed[$field] = self::toInt($field, $typed[$field]);
        }
        return $typed;
    }

    private static function toInt(string $field, mixed $value): int {
        return match (true) {
            is_int($value) => $value,
            is_string($value) && preg_match('/^-?\d+$/', $value) === 1 => (int) $value,
            default => throw new InvalidArgumentException(
                sprintf('Invalid %s value: expected integer, got %s', $field, get_debug_type($value)),
            ),
        };
    }
}
