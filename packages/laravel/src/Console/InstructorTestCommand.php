<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Console;

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Inference;
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
                            {--preset= : The preset to test (defaults to configured default)}
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
    public function handle(Inference $inference, StructuredOutput $instructor): int
    {
        $this->components->info('Testing Instructor installation...');
        $this->newLine();

        $preset = $this->option('preset') ?? config('instructor.default', 'openai');

        // Show configuration
        $this->showConfiguration($preset);

        // Test the connection
        if ($this->option('inference')) {
            return $this->testInference($inference, $preset);
        }

        return $this->testStructuredOutput($instructor, $preset);
    }

    /**
     * Show current configuration.
     */
    protected function showConfiguration(string $preset): void
    {
        $connection = config("instructor.connections.{$preset}", []);

        $this->components->twoColumnDetail('Preset', $preset);
        $this->components->twoColumnDetail('Driver', $connection['driver'] ?? 'unknown');
        $this->components->twoColumnDetail('Model', $connection['model'] ?? 'default');
        $this->components->twoColumnDetail('API Key', $this->maskApiKey($connection['api_key'] ?? ''));

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
    protected function testInference(Inference $inference, string $preset): int
    {
        $this->components->task('Testing raw inference', function () use ($inference, $preset) {
            try {
                $response = $inference
                    ->using($preset)
                    ->with(messages: 'Reply with just the word "pong".')
                    ->get();

                return str_contains(strtolower($response), 'pong');
            } catch (Throwable $e) {
                $this->newLine();
                $this->components->error('Error: ' . $e->getMessage());
                return false;
            }
        });

        $this->newLine();
        $this->components->info('Inference test completed!');

        return self::SUCCESS;
    }

    /**
     * Test structured output.
     */
    protected function testStructuredOutput(StructuredOutput $instructor, string $preset): int
    {
        $this->components->task('Testing structured output extraction', function () use ($instructor, $preset) {
            try {
                // Simple extraction test using array response model
                $result = $instructor
                    ->using($preset)
                    ->with(
                        messages: 'Extract the name and age: John Smith is 30 years old.',
                        responseModel: [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string', 'description' => 'Person name'],
                                'age' => ['type' => 'integer', 'description' => 'Person age'],
                            ],
                            'required' => ['name', 'age'],
                        ],
                    )
                    ->get();

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

        return self::SUCCESS;
    }
}
