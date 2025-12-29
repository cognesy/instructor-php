<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * Generate a new Instructor response model class.
 *
 * Creates a DTO class with PHP 8.2+ typed properties that can be
 * used as a response model for structured output extraction.
 */
#[AsCommand(name: 'make:response-model')]
class MakeResponseModelCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:response-model';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Instructor response model class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Response Model';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        if ($this->option('collection')) {
            return $this->resolveStubPath('/stubs/response-model.collection.stub');
        }

        if ($this->option('nested')) {
            return $this->resolveStubPath('/stubs/response-model.nested.stub');
        }

        return $this->resolveStubPath('/stubs/response-model.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     */
    protected function resolveStubPath(string $stub): string
    {
        $customPath = $this->laravel->basePath(trim($stub, '/'));

        if (file_exists($customPath)) {
            return $customPath;
        }

        return __DIR__ . '/../../resources' . $stub;
    }

    /**
     * Get the default namespace for the class.
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\\ResponseModels';
    }

    /**
     * Build the class with the given name.
     */
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        return $this->replaceDescription($stub);
    }

    /**
     * Replace the description placeholder.
     */
    protected function replaceDescription(string $stub): string
    {
        $description = $this->option('description')
            ?? 'TODO: Add description for this response model';

        return str_replace('{{ description }}', $description, $stub);
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['collection', 'c', InputOption::VALUE_NONE, 'Create a collection response model with an array of items'],
            ['nested', 'n', InputOption::VALUE_NONE, 'Create a nested response model with child objects'],
            ['description', 'd', InputOption::VALUE_OPTIONAL, 'The description for the response model'],
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the response model already exists'],
        ];
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['name', \Symfony\Component\Console\Input\InputArgument::REQUIRED, 'The name of the response model'],
        ];
    }
}
