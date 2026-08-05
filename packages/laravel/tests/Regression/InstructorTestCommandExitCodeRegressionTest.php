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
    use Cognesy\Http\Contracts\CanSendHttpRequests;
    use Cognesy\Http\HttpClient;
    use Cognesy\Instructor\Config\StructuredOutputConfig;
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

        public function runInference(CanProvideConfig $configProvider, CanHandleEvents $events, CanSendHttpRequests $httpClient): int
        {
            return $this->testInference('test', $configProvider, $events, $httpClient);
        }

        public function runStructuredOutput(CanProvideConfig $configProvider, CanHandleEvents $events, CanSendHttpRequests $httpClient): int
        {
            return $this->testStructuredOutput('test', $configProvider, $events, $httpClient);
        }
    }

    /** @param array<string, mixed> $values */
    function commandConfigProvider(array $values = []): CanProvideConfig
    {
        return new class($values) implements CanProvideConfig {
            /** @param array<string, mixed> $values */
            public function __construct(private array $values) {}

            public function get(string $path, mixed $default = null): mixed
            {
                return $this->values[$path] ?? $default;
            }

            public function has(string $path): bool
            {
                return array_key_exists($path, $this->values);
            }
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
                HttpClient::default(),
            );

        expect($code)->toBe(\Illuminate\Console\Command::SUCCESS);
    });

    it('returns failure code when inference task fails', function () {
        $code = (new TestableInstructorTestCommand())
            ->withTaskResult(false)
            ->runInference(
                commandConfigProvider(),
                commandEventBus(),
                HttpClient::default(),
            );

        expect($code)->toBe(\Illuminate\Console\Command::FAILURE);
    });

    it('returns success code when structured output task succeeds', function () {
        $code = (new TestableInstructorTestCommand())
            ->withTaskResult(true)
            ->runStructuredOutput(
                commandConfigProvider(),
                commandEventBus(),
                HttpClient::default(),
            );

        expect($code)->toBe(\Illuminate\Console\Command::SUCCESS);
    });

    it('returns failure code when structured output task fails', function () {
        $code = (new TestableInstructorTestCommand())
            ->withTaskResult(false)
            ->runStructuredOutput(
                commandConfigProvider(),
                commandEventBus(),
                HttpClient::default(),
            );

        expect($code)->toBe(\Illuminate\Console\Command::FAILURE);
    });

    it('maps retry prompt class into the structured output command config', function () {
        $promptClass = 'App\\Prompts\\RetryFeedbackPrompt';
        $method = new ReflectionMethod(InstructorTestCommand::class, 'resolveStructuredOutputConfig');

        /** @var StructuredOutputConfig $config */
        $config = $method->invoke(
            new TestableInstructorTestCommand(),
            commandConfigProvider([
                'instructor.extraction.retry_prompt_class' => $promptClass,
                'instructor.extraction.retry_prompt' => 'Legacy inline retry text',
            ]),
        );

        expect($config->retryPromptClass())->toBe($promptClass)
            ->and($config->toArray())->not->toHaveKey('retryPrompt');
    });
}
