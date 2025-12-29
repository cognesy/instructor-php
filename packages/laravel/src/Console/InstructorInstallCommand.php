<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Console;

use Illuminate\Console\Command;

/**
 * Install Instructor for Laravel.
 *
 * Publishes configuration and sets up the package for use.
 */
class InstructorInstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'instructor:install
                            {--force : Overwrite existing configuration files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Instructor for Laravel';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->components->info('Installing Instructor for Laravel...');

        // Publish configuration
        $this->publishConfiguration();

        // Check for API keys
        $this->checkApiKeys();

        // Show next steps
        $this->showNextSteps();

        $this->newLine();
        $this->components->info('Instructor installed successfully!');

        return self::SUCCESS;
    }

    /**
     * Publish the configuration file.
     */
    protected function publishConfiguration(): void
    {
        $params = [
            '--provider' => 'Cognesy\Instructor\Laravel\InstructorServiceProvider',
            '--tag' => 'instructor-config',
        ];

        if ($this->option('force')) {
            $params['--force'] = true;
        }

        $this->call('vendor:publish', $params);
    }

    /**
     * Check if API keys are configured.
     */
    protected function checkApiKeys(): void
    {
        $this->newLine();
        $this->components->task('Checking API key configuration', function () {
            $envPath = $this->laravel->basePath('.env');

            if (!file_exists($envPath)) {
                return false;
            }

            $env = file_get_contents($envPath);

            // Check for common API keys
            $hasOpenAI = str_contains($env, 'OPENAI_API_KEY=') && !str_contains($env, 'OPENAI_API_KEY=your-');
            $hasAnthropic = str_contains($env, 'ANTHROPIC_API_KEY=') && !str_contains($env, 'ANTHROPIC_API_KEY=your-');

            return $hasOpenAI || $hasAnthropic;
        });

        if (!$this->apiKeyConfigured()) {
            $this->newLine();
            $this->components->warn('No API keys detected in .env file.');
            $this->line('  Add at least one of these to your .env file:');
            $this->line('  • OPENAI_API_KEY=your-openai-api-key');
            $this->line('  • ANTHROPIC_API_KEY=your-anthropic-api-key');
        }
    }

    /**
     * Check if any API key is configured.
     */
    protected function apiKeyConfigured(): bool
    {
        return !empty(config('instructor.connections.openai.api_key'))
            || !empty(config('instructor.connections.anthropic.api_key'));
    }

    /**
     * Show next steps.
     */
    protected function showNextSteps(): void
    {
        $this->newLine();
        $this->components->info('Next steps:');
        $this->newLine();

        $this->line('  1. Configure your API keys in <comment>.env</comment>:');
        $this->line('     OPENAI_API_KEY=your-key-here');
        $this->newLine();

        $this->line('  2. Create a response model:');
        $this->line('     <comment>php artisan make:response-model PersonData</comment>');
        $this->newLine();

        $this->line('  3. Extract structured data:');
        $this->line('     <comment>$person = Instructor::with(</comment>');
        $this->line('         <comment>messages: "John is 30 years old",</comment>');
        $this->line('         <comment>responseModel: PersonData::class,</comment>');
        $this->line('     <comment>)->get();</comment>');
        $this->newLine();

        $this->line('  4. Test your installation:');
        $this->line('     <comment>php artisan instructor:test</comment>');
    }
}
