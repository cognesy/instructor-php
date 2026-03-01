<?php declare(strict_types=1);

namespace Illuminate\Console {
    if (!class_exists(Command::class)) {
        class Command
        {
            public const SUCCESS = 0;
            public const FAILURE = 1;

            public object $components;
            public object $output;

            /** @var array<string, mixed> */
            protected array $options = [];

            public function option(string $key): mixed
            {
                return $this->options[$key] ?? null;
            }

            public function newLine(): void {}
            public function line(string $string): void {}
        }
    }
}

namespace {
    use Cognesy\Config\Contracts\CanProvideConfig;
    use Cognesy\Events\Contracts\CanHandleEvents;
    use Cognesy\Http\HttpClient;
    use Cognesy\Instructor\Laravel\Console\InstructorTestCommand;

    final class TestConsoleComponents
    {
        public function __construct(private bool $taskResult) {}

        public function info(string $message): void {}
        public function error(string $message): void {}
        public function twoColumnDetail(string $label, mixed $value): void {}

        public function task(string $description, callable $task): bool
        {
            return $this->taskResult;
        }
    }

    final class TestConsoleOutput
    {
        public function isVerbose(): bool
        {
            return false;
        }
    }

    final class TestableInstructorTestCommand extends InstructorTestCommand
    {
        public function withTaskResult(bool $taskResult): self
        {
            $this->components = new TestConsoleComponents($taskResult);
            $this->output = new TestConsoleOutput();
            return $this;
        }

        public function runInference(CanProvideConfig $configProvider, CanHandleEvents $events, HttpClient $httpClient): int
        {
            return $this->testInference('test', $configProvider, $events, $httpClient);
        }

        public function runStructuredOutput(CanProvideConfig $configProvider, CanHandleEvents $events, HttpClient $httpClient): int
        {
            return $this->testStructuredOutput('test', $configProvider, $events, $httpClient);
        }
    }

    function commandConfigProvider(): CanProvideConfig
    {
        return new class implements CanProvideConfig {
            public function get(string $path, mixed $default = null): mixed { return $default; }
            public function has(string $path): bool { return false; }
        };
    }

    function commandEventBus(): CanHandleEvents
    {
        return new class implements CanHandleEvents {
            public function addListener(string $name, callable $listener, int $priority = 0): void {}
            public function wiretap(callable $listener): void {}
            public function dispatch(object $event): object { return $event; }
            public function getListenersForEvent(object $event): iterable { return []; }
        };
    }

    it('returns success code when inference task succeeds', function () {
        $code = (new TestableInstructorTestCommand())
            ->withTaskResult(true)
            ->runInference(
                commandConfigProvider(),
                commandEventBus(),
                new HttpClient(),
            );

        expect($code)->toBe(\Illuminate\Console\Command::SUCCESS);
    });

    it('returns failure code when inference task fails', function () {
        $code = (new TestableInstructorTestCommand())
            ->withTaskResult(false)
            ->runInference(
                commandConfigProvider(),
                commandEventBus(),
                new HttpClient(),
            );

        expect($code)->toBe(\Illuminate\Console\Command::FAILURE);
    });

    it('returns success code when structured output task succeeds', function () {
        $code = (new TestableInstructorTestCommand())
            ->withTaskResult(true)
            ->runStructuredOutput(
                commandConfigProvider(),
                commandEventBus(),
                new HttpClient(),
            );

        expect($code)->toBe(\Illuminate\Console\Command::SUCCESS);
    });

    it('returns failure code when structured output task fails', function () {
        $code = (new TestableInstructorTestCommand())
            ->withTaskResult(false)
            ->runStructuredOutput(
                commandConfigProvider(),
                commandEventBus(),
                new HttpClient(),
            );

        expect($code)->toBe(\Illuminate\Console\Command::FAILURE);
    });
}
