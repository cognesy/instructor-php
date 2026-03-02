<?php declare(strict_types=1);

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Event;
use Cognesy\Logging\Integrations\Laravel\InstructorLoggingServiceProvider;
use Illuminate\Contracts\Foundation\Application;

final readonly class LoggingConfigRepository
{
    public function __construct(private array $config) {}

    public function get(string $path, mixed $default = null): mixed
    {
        $parts = explode('.', $path);
        $value = $this->config;
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }

        return $value;
    }
}

afterEach(function (): void {
    Mockery::close();
});

it('wires logging pipeline to configured event bus binding', function () {
    $config = new LoggingConfigRepository([
        'instructor-logging' => [
            'enabled' => true,
            'event_bus_binding' => CanHandleEvents::class,
        ],
    ]);

    $eventBus = Mockery::mock(CanHandleEvents::class);
    $capturedListener = null;
    $eventBus->shouldReceive('wiretap')
        ->once()
        ->with(Mockery::on(function ($listener) use (&$capturedListener): bool {
            $capturedListener = $listener;
            return is_callable($listener);
        }));

    $app = Mockery::mock(Application::class);
    $app->shouldReceive('runningInConsole')->andReturn(false);
    $app->shouldReceive('bound')->with('config')->andReturn(true);
    $app->shouldReceive('make')->with('config')->andReturn($config);
    $app->shouldReceive('bound')->with(CanHandleEvents::class)->andReturn(true);
    $app->shouldReceive('make')->with(CanHandleEvents::class)->andReturn($eventBus);
    $app->shouldNotReceive('afterResolving');

    $logged = [];
    $provider = new class($app, $logged) extends InstructorLoggingServiceProvider {
        /** @var list<Event> */
        public array $logged = [];

        public function __construct(Application $app, array &$logged)
        {
            parent::__construct($app);
            $this->logged = &$logged;
        }

        protected function makePipeline(): callable
        {
            return function (Event $event): void {
                $this->logged[] = $event;
            };
        }
    };

    $provider->boot();

    expect($capturedListener)->toBeCallable();

    $capturedListener(new stdClass());
    $capturedListener(new Event());

    expect($provider->logged)->toHaveCount(1)
        ->and($provider->logged[0])->toBeInstanceOf(Event::class);
});

it('skips wiring when configured event bus binding is unavailable', function () {
    $config = new LoggingConfigRepository([
        'instructor-logging' => [
            'enabled' => true,
            'event_bus_binding' => CanHandleEvents::class,
        ],
    ]);

    $app = Mockery::mock(Application::class);
    $app->shouldReceive('runningInConsole')->andReturn(false);
    $app->shouldReceive('bound')->with('config')->andReturn(true);
    $app->shouldReceive('make')->with('config')->andReturn($config);
    $app->shouldReceive('bound')->with(CanHandleEvents::class)->andReturn(false);

    $provider = new class($app) extends InstructorLoggingServiceProvider {
        protected function makePipeline(): callable
        {
            return static function (Event $event): void {};
        }
    };

    $provider->boot();

    expect(true)->toBeTrue();
});
