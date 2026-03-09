<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Console;

use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Illuminate\Console\Command;
use Throwable;

/**
 * Test Instructor installation and configuration.
 *
 * Verifies that API keys are configured correctly and the LLM
 * connection is working.
 */
class InstructorTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'instructor:test
                            {--connection= : The connection to test (defaults to configured default)}
                            {--inference : Test raw inference instead of structured output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Instructor installation and API configuration';

    /**
     * Execute the console command.
     */
    public function handle(
        CanProvideConfig $configProvider,
        CanHandleEvents $events,
        CanSendHttpRequests $httpClient,
    ): int
    {
        $this->components->info('Testing Instructor installation...');
        $this->newLine();

        $connection = $this->option('connection') ?? config('instructor.default', 'openai');

        // Show configuration
        $this->showConfiguration($connection);

        // Test the connection
        if ($this->option('inference')) {
            return $this->testInference($connection, $configProvider, $events, $httpClient);
        }

        return $this->testStructuredOutput($connection, $configProvider, $events, $httpClient);
    }

    /**
     * Show current configuration.
     */
    protected function showConfiguration(string $connection): void
    {
        $connectionConfig = config("instructor.connections.{$connection}", []);

        $this->components->twoColumnDetail('Connection', $connection);
        $this->components->twoColumnDetail('Driver', $connectionConfig['driver'] ?? 'unknown');
        $this->components->twoColumnDetail('Model', $connectionConfig['model'] ?? 'default');
        $this->components->twoColumnDetail('API Key', $this->maskApiKey($connectionConfig['api_key'] ?? ''));

        $this->newLine();
    }

    /**
     * Mask API key for display.
     */
    protected function maskApiKey(string $key): string
    {
        if (empty($key)) {
            return '<fg=red>Not configured</>';
        }

        if (strlen($key) < 10) {
            return '<fg=yellow>Invalid (too short)</>';
        }

        return substr($key, 0, 4) . '...' . substr($key, -4) . ' <fg=green>✓</>';
    }

    /**
     * Test raw inference.
     */
    protected function testInference(
        string $connection,
        CanProvideConfig $configProvider,
        CanHandleEvents $events,
        CanSendHttpRequests $httpClient,
    ): int {
        $ok = $this->components->task('Testing raw inference', function () use ($connection, $configProvider, $events, $httpClient) {
            try {
                $response = InferenceRuntime::fromConfig(
                    config: $this->resolveLLMConfig($connection, $configProvider),
                    events: $events,
                    httpClient: $httpClient,
                )->create(new InferenceRequest(
                    messages: 'Reply with just the word "pong".',
                ))->get();

                return str_contains(strtolower($response), 'pong');
            } catch (Throwable $e) {
                $this->newLine();
                $this->components->error('Error: ' . $e->getMessage());
                return false;
            }
        });

        $this->newLine();
        $this->components->info('Inference test completed!');

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Test structured output.
     */
    protected function testStructuredOutput(
        string $connection,
        CanProvideConfig $configProvider,
        CanHandleEvents $events,
        CanSendHttpRequests $httpClient,
    ): int {
        $ok = $this->components->task('Testing structured output extraction', function () use ($connection, $configProvider, $events, $httpClient) {
            try {
                // Simple extraction test using array response model
                $result = StructuredOutputRuntime::fromConfig(
                    config: $this->resolveLLMConfig($connection, $configProvider),
                    events: $events,
                    httpClient: $httpClient,
                    structuredConfig: $this->resolveStructuredOutputConfig($configProvider),
                )->create(new StructuredOutputRequest(
                    messages: 'Extract the name and age: John Smith is 30 years old.',
                    requestedSchema: [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string', 'description' => 'Person name'],
                            'age' => ['type' => 'integer', 'description' => 'Person age'],
                        ],
                        'required' => ['name', 'age'],
                    ],
                ))->get();

                // Verify we got a result
                if (is_array($result)) {
                    return isset($result['name']) && isset($result['age']);
                }

                if (is_object($result)) {
                    return isset($result->name) && isset($result->age);
                }

                return false;
            } catch (Throwable $e) {
                $this->newLine();
                $this->components->error('Error: ' . $e->getMessage());

                if ($this->output->isVerbose()) {
                    $this->line($e->getTraceAsString());
                }

                return false;
            }
        });

        $this->newLine();
        $this->components->info('Structured output test completed!');

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function resolveLLMConfig(string $connection, CanProvideConfig $configProvider): LLMConfig {
        $raw = $configProvider->get("instructor.connections.{$connection}", []);
        $config = is_array($raw) ? $raw : [];
        $driver = (string) ($config['driver'] ?? $connection ?: 'openai');
        $model = (string) ($config['model'] ?? '');
        $endpoint = (string) ($config['endpoint'] ?? $this->defaultLlmEndpoint($driver, $model));

        return LLMConfig::fromArray([
            'driver' => $driver,
            'apiUrl' => (string) ($config['api_url'] ?? ''),
            'apiKey' => (string) ($config['api_key'] ?? ''),
            'endpoint' => $endpoint,
            'model' => $model,
            'maxTokens' => (int) ($config['max_tokens'] ?? 4096),
        ]);
    }

    private function resolveStructuredOutputConfig(CanProvideConfig $configProvider): StructuredOutputConfig {
        $maxRetries = $configProvider->get('instructor.extraction.max_retries');
        $outputMode = $configProvider->get('instructor.extraction.output_mode');
        $retryPrompt = $configProvider->get('instructor.extraction.retry_prompt');

        $data = [];
        if (is_int($maxRetries) || is_numeric($maxRetries)) {
            $data['maxRetries'] = (int) $maxRetries;
        }
        if (is_string($outputMode) && $outputMode !== '') {
            $data['outputMode'] = $outputMode;
        }
        if (is_string($retryPrompt) && $retryPrompt !== '') {
            $data['retryPrompt'] = $retryPrompt;
        }

        return StructuredOutputConfig::fromArray($data);
    }

    private function defaultLlmEndpoint(string $driver, string $model): string {
        return match ($driver) {
            'anthropic' => '/messages',
            'gemini' => "/models/{$model}:generateContent",
            default => '/chat/completions',
        };
    }
}
