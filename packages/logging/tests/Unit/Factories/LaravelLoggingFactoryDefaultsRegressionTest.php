<?php declare(strict_types=1);

namespace Cognesy\Logging\Tests\Unit\Factories\LaravelLoggingFactoryDefaultsRegressionTest;

use ArrayAccess;
use Cognesy\Events\Event;
use Cognesy\Logging\Factories\LaravelLoggingFactory;
use Mockery;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;
use Illuminate\Contracts\Foundation\Application;

final class InMemoryLogger extends AbstractLogger
{
    /** @var array<int, array{level: string, message: string, context: array}> */
    public array $records = [];

    public function log($level, Stringable|string $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}

final readonly class TestLogManager
{
    public function __construct(private InMemoryLogger $logger) {}

    public function channel(string $channel): InMemoryLogger
    {
        return $this->logger;
    }
}

final readonly class TestConfigRepository
{
    public function __construct(private array $items = []) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}

function makeLaravelAppMock(array $config, InMemoryLogger $logger): object
{
    $app = Mockery::mock(Application::class . ',' . ArrayAccess::class);
    $app->shouldReceive('bound')->with('config')->andReturn(true);
    $app->shouldReceive('make')->with('config')->andReturn(new TestConfigRepository($config));
    $app->shouldReceive('bound')->with('request')->andReturn(false);
    $app->shouldReceive('offsetGet')->with('log')->andReturn(new TestLogManager($logger));

    return $app;
}

afterEach(function () {
    Mockery::close();
});

it('does not log debug events in default setup without explicit debug configuration', function () {
    $logger = new InMemoryLogger();
    /** @var Application&ArrayAccess<string, mixed> $app */
    $app = makeLaravelAppMock(config: [], logger: $logger);

    $pipeline = LaravelLoggingFactory::defaultSetup($app);
    $pipeline(new Event(['sensitive' => 'payload']));

    expect($logger->records)->toHaveCount(0);
});

it('keeps warning-level visibility in default setup', function () {
    $logger = new InMemoryLogger();
    /** @var Application&ArrayAccess<string, mixed> $app */
    $app = makeLaravelAppMock(config: [], logger: $logger);

    $pipeline = LaravelLoggingFactory::defaultSetup($app);

    $event = new Event(['signal' => 'warning']);
    $event->logLevel = LogLevel::WARNING;
    $pipeline($event);

    expect($logger->records)->toHaveCount(1)
        ->and($logger->records[0]['level'])->toBe(LogLevel::WARNING);
});
