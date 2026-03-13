<?php declare(strict_types=1);

use Cognesy\Instructor\Laravel\Console\MakeResponseModelCommand;
use Illuminate\Container\Container;
use Symfony\Component\Filesystem\Filesystem;

if (!class_exists('GeneratorCommandTestStub', false)) {
    class GeneratorCommandTestStub
    {
        protected mixed $laravel = null;

        public function setLaravel(mixed $laravel): void
        {
            $this->laravel = $laravel;
        }

        protected function option(string $key): mixed
        {
            return null;
        }

        protected function buildClass(mixed $name): string
        {
            return '';
        }
    }
}

if (!class_exists(\Illuminate\Console\GeneratorCommand::class)) {
    class_alias(GeneratorCommandTestStub::class, \Illuminate\Console\GeneratorCommand::class);
}

if (!class_exists(TestableMakeResponseModelCommand::class, false)) {
    final class TestableMakeResponseModelCommand extends MakeResponseModelCommand
    {
        public function resolveStubPublic(string $stub): string
        {
            return $this->resolveStubPath($stub);
        }
    }
}

if (!class_exists(StubResolutionContainer::class, false)) {
    final class StubResolutionContainer extends Container
    {
        public function __construct(private readonly string $basePath) {}

        public function basePath(string $path = ''): string
        {
            return $path === ''
                ? $this->basePath
                : $this->basePath . '/' . ltrim($path, '/');
        }
    }
}

it('uses published stubs from stubs/instructor before package defaults', function () {
    $filesystem = new Filesystem();
    $tempBasePath = sys_get_temp_dir() . '/instructor-myxi-' . uniqid('', true);
    $customStubPath = $tempBasePath . '/stubs/instructor/response-model.stub';

    $filesystem->mkdir($tempBasePath . '/stubs/instructor');
    $filesystem->dumpFile($customStubPath, 'custom response-model stub');

    $command = new TestableMakeResponseModelCommand();
    $command->setLaravel(new StubResolutionContainer($tempBasePath));

    try {
        $resolvedPath = $command->resolveStubPublic('/stubs/response-model.stub');
        expect($resolvedPath)->toBe($customStubPath);
    } finally {
        $filesystem->remove($tempBasePath);
    }
});

it('falls back to package default stubs when no published stub exists', function () {
    $tempBasePath = sys_get_temp_dir() . '/instructor-myxi-fallback-' . uniqid('', true);
    $filesystem = new Filesystem();
    $filesystem->mkdir($tempBasePath);

    $command = new TestableMakeResponseModelCommand();
    $command->setLaravel(new StubResolutionContainer($tempBasePath));

    try {
        $resolvedPath = $command->resolveStubPublic('/stubs/response-model.stub');
        $commandPath = dirname((new ReflectionClass(MakeResponseModelCommand::class))->getFileName());
        $expectedPath = $commandPath . '/../../resources/stubs/response-model.stub';

        expect($resolvedPath)->toBe($expectedPath);
    } finally {
        $filesystem->remove($tempBasePath);
    }
});
