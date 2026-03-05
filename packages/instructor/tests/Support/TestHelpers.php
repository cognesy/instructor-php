<?php declare(strict_types=1);

// Shared test helpers for Instructor package

if (!function_exists('makeAnyResponseModel')) {
    function makeAnyResponseModel(mixed $any): \Cognesy\Instructor\Data\ResponseModel {
        $config = new \Cognesy\Instructor\Config\StructuredOutputConfig();
        $events = new \Cognesy\Events\Dispatchers\EventDispatcher();
        $factory = new \Cognesy\Instructor\Creation\ResponseModelFactory(
            new \Cognesy\Instructor\Creation\StructuredOutputSchemaRenderer($config),
            $config,
            $events,
        );
        return $factory->fromAny($any);
    }
}

if (!function_exists('makeStructuredRuntime')) {
    /**
     * @param array<\Cognesy\Instructor\Validation\Contracts\CanValidateObject|string> $validators
     * @param array<\Cognesy\Instructor\Transformation\Contracts\CanTransformData|string> $transformers
     * @param array<\Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass|string> $deserializers
     * @param array<\Cognesy\Instructor\Extraction\Contracts\CanExtractResponse|string> $extractors
     */
    function makeStructuredRuntime(
        ?\Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest $driver = null,
        ?\Cognesy\Events\Contracts\CanHandleEvents $events = null,
        ?\Cognesy\Http\HttpClient $httpClient = null,
        ?string $llmDriver = null,
        ?\Cognesy\Instructor\Config\StructuredOutputConfig $config = null,
        array $extractors = [],
        array $validators = [],
        array $transformers = [],
        array $deserializers = [],
    ): \Cognesy\Instructor\StructuredOutputRuntime {
        $provider = \Cognesy\Polyglot\Inference\LLMProvider::fromLLMConfig(match (true) {
            $llmDriver !== null => makeLLMConfigForDriver($llmDriver),
            default => \Cognesy\Polyglot\Inference\Config\LLMConfig::fromArray([]),
        });
        if ($driver !== null) {
            $provider = $provider->withDriver($driver);
        }

        $runtime = \Cognesy\Instructor\StructuredOutputRuntime::fromProvider(
            provider: $provider,
            events: $events,
            httpClient: $httpClient,
            structuredConfig: $config,
        );

        if (!empty($validators)) {
            $runtime = $runtime->withValidators($validators);
        }
        if (!empty($transformers)) {
            $runtime = $runtime->withTransformers($transformers);
        }
        if (!empty($deserializers)) {
            $runtime = $runtime->withDeserializers($deserializers);
        }
        if (!empty($extractors)) {
            $runtime = $runtime->withExtractors($extractors);
        }
        return $runtime;
    }
}

if (!function_exists('makeLLMConfigForDriver')) {
    function makeLLMConfigForDriver(string $driver): \Cognesy\Polyglot\Inference\Config\LLMConfig {
        return match ($driver) {
            'openai', 'openai-compatible' => new \Cognesy\Polyglot\Inference\Config\LLMConfig(
                driver: $driver,
                apiUrl: 'https://api.openai.com/v1',
                apiKey: 'test',
                endpoint: '/chat/completions',
                model: 'gpt-4o-mini',
            ),
            'anthropic' => new \Cognesy\Polyglot\Inference\Config\LLMConfig(
                driver: 'anthropic',
                apiUrl: 'https://api.anthropic.com/v1',
                apiKey: 'test',
                endpoint: '/messages',
                model: 'claude-3-5-sonnet-latest',
            ),
            default => \Cognesy\Polyglot\Inference\Config\LLMConfig::fromArray(['driver' => $driver]),
        };
    }
}
