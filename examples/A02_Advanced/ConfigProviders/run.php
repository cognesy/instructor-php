---
title: 'Use custom configuration providers'
docname: 'config_providers'
---

## Overview

You can inject your own configuration providers to StructuredOutput class.
This is useful for integration with your preferred framework (e.g. Symfony,
Laravel).

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Http\Contracts\CanProvideDebugConfig;
use Cognesy\Http\Contracts\CanProvideHttpClientConfig;
use Cognesy\Http\Data\HttpClientConfig;
use Cognesy\Http\Debug\DebugConfig;
use Cognesy\Http\HttpClientBuilder;
use Cognesy\Instructor\Contracts\CanProvideStructuredOutputConfig;
use Cognesy\Instructor\Data\StructuredOutputConfig;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\LLM\Contracts\CanProvideLLMConfig;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Config\Env;
use Cognesy\Utils\Events\EventDispatcher;
use Symfony\Component\HttpClient\HttpClient as SymfonyHttpClient;

class User {
    public int $age;
    public string $name;
}

$events = new EventDispatcher();

// Let's build a set of custom configuration providers.
// You can use those examples to build your framework-specific providers.

class CustomHttpConfigProvider implements CanProvideHttpClientConfig
{
    public function getConfig(?string $preset = ''): HttpClientConfig {
        return new HttpClientConfig(
            driver: 'symfony',
            connectTimeout: 10,
            requestTimeout: 30,
            idleTimeout: -1,
            maxConcurrent: 5,
            poolTimeout: 60,
            failOnError: true,
        );
    }
}

class CustomLLMConfigProvider implements CanProvideLLMConfig {
    public function getConfig(?string $preset = null): LLMConfig {
        return $this->presets()[$preset] ?? new LLMConfig();
    }

    private function presets(): array {
        return [
            'deepseek' => new LLMConfig(
                apiUrl  : 'https://api.deepseek.com',
                apiKey  : Env::get('DEEPSEEK_API_KEY'),
                endpoint: '/chat/completions', defaultModel: 'deepseek-chat', defaultMaxTokens: 128,
                driver  : 'deepseek',
            ),
            // ...
        ];
    }
};

class CustomDebugConfigProvider implements CanProvideDebugConfig
{
    public function getConfig(?string $preset = ''): DebugConfig {
        return $this->presets()[$preset] ?? new DebugConfig();
    }

    private function presets(): array {
        return [
            'off' => new DebugConfig(
                httpEnabled: true,
            ),
            'on' => new DebugConfig(
                httpEnabled: true,
                httpTrace: true,
                httpRequestUrl: true,
                httpRequestHeaders: true,
                httpRequestBody: true,
                httpResponseHeaders: true,
                httpResponseBody: true,
                httpResponseStream: true,
                httpResponseStreamByLine: true,
            )
        ];
    }
}

// check SettingsStructuredOutputConfigProvider for a reference implementation
class CustomStructuredOutputConfigProvider implements CanProvideStructuredOutputConfig
{
    public function getConfig(?string $preset = null): StructuredOutputConfig {
        return $this->presets()[$preset] ?? new StructuredOutputConfig();
    }

    private function presets(): array {
        return [
            'default' => new StructuredOutputConfig(
                outputMode: OutputMode::Tools,
                useObjectReferences: true,
                maxRetries: 3,
                retryPrompt: 'Please try again ...',
                modePrompts: [
                    OutputMode::MdJson->value => "Response must validate against this JSON Schema:\n<|json_schema|>\n. Respond correctly with strict JSON object within a ```json {} ``` codeblock.\n",
                    OutputMode::Json->value => "Response must follow JSON Schema:\n<|json_schema|>\n. Respond correctly with strict JSON object.\n",
                    OutputMode::JsonSchema->value => "Response must follow provided JSON Schema. Respond correctly with strict JSON object.\n",
                    OutputMode::Tools->value => "Extract correct and accurate data from the input using provided tools.\n",
                ],
                schemaName: 'user_schema',
                toolName: 'user_tool',
                toolDescription: 'Tool to extract user information ...',
                chatStructure: [
                    'system',
                    'pre-cached',
                        'pre-cached-prompt', 'cached-prompt', 'post-cached-prompt',
                        'pre-cached-examples', 'cached-examples', 'post-cached-examples',
                        'cached-messages',
                    'post-cached',
                    'pre-prompt', 'prompt', 'post-prompt',
                    'pre-examples', 'examples', 'post-examples',
                    'pre-messages', 'messages', 'post-messages',
                    'pre-retries', 'retries', 'post-retries'
                ],
                defaultOutputClass: Structure::class,
            ),
        ];
    }
};

$customClient = (new HttpClientBuilder(
        events: $events,
        listener: $events,
        httpConfigProvider: new CustomHttpConfigProvider(),
        debugConfigProvider: new CustomDebugConfigProvider(),
    ))
    ->withClientInstance(SymfonyHttpClient::create(['http_version' => '2.0']))
    ->withDebugPreset('on')
    ->create();

// Get Instructor with the default client component overridden with your own

$structuredOutput = (new StructuredOutput(
        events: $events,
        listener: $events,
        configProvider: new CustomStructuredOutputConfigProvider(),
        llmConfigProvider: new CustomLLMConfigProvider(),
    ))
    ->withHttpClient($customClient);

// Call with custom model and execution mode

$user = $structuredOutput->using('deepseek') // Use 'deepseek' preset defined in CustomLLMConfigProvider
    ->wiretap(fn($e) => $e->print())
    ->with(
        messages: "Our user Jason is 25 years old.",
        responseModel: User::class,
        mode: OutputMode::Tools,
    )
    ->withStreaming()
    ->get();

dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
