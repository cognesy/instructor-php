<?php declare(strict_types=1);

use Cognesy\Instructor\Laravel\Facades\StructuredOutput as StructuredOutputFacade;
use Cognesy\Instructor\Laravel\InstructorServiceProvider;
use Cognesy\Instructor\Laravel\Testing\StructuredOutputFake;
use Cognesy\Instructor\StructuredOutput;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as LaravelDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;

final class StructuredOutputFacadeFakeMinimalConfigRepository
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

    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $cursor =& $this->items;
        $last = array_pop($segments);

        if ($last === null) {
            return;
        }

        foreach ($segments as $segment) {
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }

            $cursor =& $cursor[$segment];
        }

        $cursor[$last] = $value;
    }
}

final class StructuredOutputFacadeFakeTestDispatcher implements LaravelDispatcher
{
    public function listen($events, $listener = null): void {}
    public function hasListeners($eventName): bool { return false; }
    public function subscribe($subscriber): void {}
    public function until($event, $payload = []): mixed { return null; }
    public function dispatch($event, $payload = [], $halt = false): mixed { return null; }
    public function push($event, $payload = []): void {}
    public function flush($event): void {}
    public function forget($event): void {}
    public function forgetPushed(): void {}
}

final readonly class StructuredOutputFacadeFakeSupportTicket
{
    /** @param string[] $actionItems */
    public function __construct(
        public string $priority,
        public array $actionItems,
    ) {}
}

final class StructuredOutputFacadeFakeController
{
    public function __construct(
        private StructuredOutput $structuredOutput,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $ticket = $this->structuredOutput->with(
            messages: (string) $request->input('message'),
            responseModel: StructuredOutputFacadeFakeSupportTicket::class,
        )->get();

        return new JsonResponse($ticket);
    }
}

it('keeps constructor injection typed as StructuredOutput working after fake activation', function () {
    $app = new Container();
    $app->instance('config', new StructuredOutputFacadeFakeMinimalConfigRepository([
        'instructor' => [
            'logging' => ['enabled' => false],
        ],
    ]));
    $app->instance(LaravelDispatcher::class, new StructuredOutputFacadeFakeTestDispatcher());

    Container::setInstance($app);
    Facade::setFacadeApplication($app);
    Facade::clearResolvedInstances();

    (new InstructorServiceProvider($app))->register();

    $fake = StructuredOutputFacade::fake([
        StructuredOutputFacadeFakeSupportTicket::class => new StructuredOutputFacadeFakeSupportTicket(
            priority: 'high',
            actionItems: ['Confirm the duplicate invoice'],
        ),
    ]);

    $controller = $app->make(StructuredOutputFacadeFakeController::class);
    $response = $controller(Request::create(
        uri: '/support/triage',
        method: 'POST',
        content: json_encode(['message' => 'Customer was charged twice.'], JSON_THROW_ON_ERROR),
        server: ['CONTENT_TYPE' => 'application/json'],
    ));
    $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

    expect($fake)->toBeInstanceOf(StructuredOutputFake::class)
        ->and($app->make(StructuredOutput::class))->toBeInstanceOf(StructuredOutput::class)
        ->and($payload['priority'])->toBe('high')
        ->and($payload['actionItems'])->toBe(['Confirm the duplicate invoice'])
        ->and($fake->recorded())->toHaveCount(1)
        ->and($fake->recorded()[0]['class'])->toBe(StructuredOutputFacadeFakeSupportTicket::class);
});
