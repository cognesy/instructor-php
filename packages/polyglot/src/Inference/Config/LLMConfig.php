<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Config;

use Cognesy\Config\Exceptions\ConfigurationException;
use Cognesy\Polyglot\Inference\Data\Pricing;
use InvalidArgumentException;
use Throwable;

final class LLMConfig
{
    public const CONFIG_GROUP = 'llm';

    public static function group() : string {
        return self::CONFIG_GROUP;
    }

    public function __construct(
        public string $apiUrl = '',
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

    public static function fromArray(array $config) : LLMConfig {
        try {
            $instance = new self(...$config);
        } catch (Throwable $e) {
            $data = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            throw new ConfigurationException(
                message: "Invalid configuration for LLMConfig: {$e->getMessage()}\nData: {$data}",
                previous: $e,
            );
        }
        return $instance;
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

    public function getPricing(): Pricing {
        return Pricing::fromArray($this->pricing);
    }

    private function assertNoRetryPolicyInOptions(array $options) : void {
        if (!array_key_exists('retryPolicy', $options) && !array_key_exists('retry_policy', $options)) {
            return;
        }

        throw new InvalidArgumentException('retryPolicy must be configured via an explicit retry policy, not LLM options.');
    }
}
