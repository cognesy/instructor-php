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
    function makeStructuredRuntime(
        ?\Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest $driver = null,
        ?\Cognesy\Events\Contracts\CanHandleEvents $events = null,
        ?\Cognesy\Http\Contracts\CanSendHttpRequests $httpClient = null,
        ?string $llmDriver = null,
        ?\Cognesy\Instructor\Config\StructuredOutputConfig $config = null,
        ?\Cognesy\Instructor\Enums\OutputMode $outputMode = null,
        ?int $maxRetries = null,
        ?bool $defaultToStdClass = null,
        ?\Cognesy\Instructor\Extraction\Contracts\CanExtractResponse $extractor = null,
        ?\Cognesy\Instructor\Validation\Contracts\CanValidateObject $validator = null,
        ?\Cognesy\Instructor\Transformation\Contracts\CanTransformData $transformer = null,
        ?\Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass $deserializer = null,
    ): \Cognesy\Instructor\StructuredOutputRuntime {
        $provider = \Cognesy\Polyglot\Inference\LLMProvider::fromLLMConfig(match (true) {
            $llmDriver !== null => makeLLMConfigForDriver($llmDriver),
            default => \Cognesy\Polyglot\Inference\Config\LLMConfig::fromArray([]),
        });
        if ($driver !== null) {
            $provider = $provider->withDriver($driver);
        }

        $structuredConfig = $config ?? new \Cognesy\Instructor\Config\StructuredOutputConfig();
        if ($outputMode !== null) {
            $structuredConfig = $structuredConfig->withOutputMode($outputMode);
        }
        if ($maxRetries !== null) {
            $structuredConfig = $structuredConfig->withMaxRetries($maxRetries);
        }
        if ($defaultToStdClass !== null) {
            $structuredConfig = $structuredConfig->with(defaultToStdClass: $defaultToStdClass);
        }

        $runtime = \Cognesy\Instructor\StructuredOutputRuntime::fromProvider(
            provider: $provider,
            events: $events,
            httpClient: $httpClient,
            structuredConfig: $structuredConfig,
        );

        if ($validator !== null) {
            $runtime = $runtime->withValidator($validator);
        }
        if ($transformer !== null) {
            $runtime = $runtime->withTransformer($transformer);
        }
        if ($deserializer !== null) {
            $runtime = $runtime->withDeserializer($deserializer);
        }
        if ($extractor !== null) {
            $runtime = $runtime->withExtractor($extractor);
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
