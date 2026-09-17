<?php

declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Creation\HttpClientDefaults;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Decision\Answers\NoulAnswer;
use Cognesy\Polyglot\Decision\Collections\Answers;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Config\DecisionRetryPolicy;
use Cognesy\Polyglot\Decision\Contracts\CanProcessDecisionRequest;
use Cognesy\Polyglot\Decision\Core\DecisionExecutionSession;
use Cognesy\Polyglot\Decision\Creation\DecisionDriverRegistry;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\DecisionResponse;
use Cognesy\Polyglot\Decision\Decision;
use Cognesy\Polyglot\Decision\DecisionRuntime;
use Cognesy\Polyglot\Decision\Exceptions\DecisionInvalidRequestException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionRateLimitException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionResponseException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionTransientException;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Support\Retry\CanDelayRetries;

it('keeps create lazy and memoizes one successful execution across projections', function () {
    $driver = new DecisionLifecycleFakeDriver(decisionLifecycleResponse(0.8));
    $runtime = decisionLifecycleRuntime($driver);
    $request = decisionLifecycleRequest();
    $pending = $runtime->create($request);

    expect($driver->calls)->toBe(0)
        ->and($pending->request()->model())->toBe('jev-default')
        ->and($pending->request()->id()->equals($request->id()))->toBeTrue()
        ->and($pending->executionId())->toMatch('/^[0-9a-f-]{36}$/');

    $answers = $pending->get();
    $response = $pending->response();

    expect($driver->calls)->toBe(1)
        ->and($answers)->toBe($response->answers())
        ->and($answers->noul('safe')->probability())->toBe(0.8)
        ->and($pending->response())->toBe($response);
});

it('makes facade and explicit runtime calls equivalent while keeping each create independent', function () {
    $driver = new DecisionLifecycleFakeDriver(
        decisionLifecycleResponse(0.6),
        decisionLifecycleResponse(0.6),
        decisionLifecycleResponse(0.6),
    );
    $runtime = decisionLifecycleRuntime($driver);
    $facade = Decision::fromRuntime($runtime)
        ->withInput('state')
        ->withQuestions(decisionLifecycleQuestions());

    $facadePending = $facade->create();
    $explicitPending = $runtime->create(decisionLifecycleRequest());
    $secondFacadePending = $facade->create();

    expect($driver->calls)->toBe(0)
        ->and($facadePending->request()->toArray())->toEqual($explicitPending->request()->toArray())
        ->and($facadePending->executionId())->not->toBe($explicitPending->executionId())
        ->and($secondFacadePending->executionId())->not->toBe($facadePending->executionId())
        ->and($facadePending->get()->noul('safe')->probability())->toBe(0.6)
        ->and($explicitPending->get()->noul('safe')->probability())->toBe(0.6)
        ->and($secondFacadePending->get()->noul('safe')->probability())->toBe(0.6)
        ->and($driver->calls)->toBe(3);
});

it('keeps facade withers immutable and preserves an explicit request identity', function () {
    $driver = new DecisionLifecycleFakeDriver(decisionLifecycleResponse(0.7));
    $runtime = decisionLifecycleRuntime($driver);
    $base = Decision::fromRuntime($runtime);
    $withInput = $base->withInput('state');
    $configured = $withInput->withQuestions(decisionLifecycleQuestions());
    $request = decisionLifecycleRequest(model: 'jev-original');
    $replaced = $base->withRequest($request)->withModel('jev-override');

    expect(fn () => $base->create())->toThrow(InvalidArgumentException::class, 'input is required')
        ->and(fn () => $withInput->create())->toThrow(InvalidArgumentException::class, 'requires at least one question')
        ->and($configured->create()->request()->model())->toBe('jev-default')
        ->and($replaced->create()->request()->model())->toBe('jev-override')
        ->and($replaced->create()->request()->id()->equals($request->id()))->toBeTrue()
        ->and($driver->calls)->toBe(0);
});

it('memoizes terminal failures and rethrows the same instance without restarting', function () {
    $error = new DecisionInvalidRequestException('synthetic terminal', 422);
    $driver = new DecisionLifecycleFakeDriver($error);
    $pending = decisionLifecycleRuntime($driver)->create(
        decisionLifecycleRequest(retryPolicy: new DecisionRetryPolicy(maxAttempts: 3, baseDelayMs: 0)),
    );

    $first = $second = null;
    try {
        $pending->response();
    } catch (Throwable $caught) {
        $first = $caught;
    }
    try {
        $pending->get();
    } catch (Throwable $caught) {
        $second = $caught;
    }

    expect($first)->toBe($error)
        ->and($second)->toBe($error)
        ->and($driver->calls)->toBe(1);
});

it('retries each supported transient Decision category', function (Throwable $error) {
    $driver = new DecisionLifecycleFakeDriver($error, decisionLifecycleResponse(0.9));
    $delay = new DecisionLifecycleFakeDelay;
    $runtime = decisionLifecycleRuntime($driver, $delay);
    $pending = $runtime->create(decisionLifecycleRequest(
        retryPolicy: new DecisionRetryPolicy(
            maxAttempts: 2,
            baseDelayMs: 10,
            maxDelayMs: 100,
            jitter: 'none',
        ),
    ));

    expect($pending->get()->noul('safe')->probability())->toBe(0.9)
        ->and($driver->calls)->toBe(2)
        ->and($delay->delays)->toBe([10]);
})->with([
    '408' => new DecisionTransientException('timeout', 408),
    '429' => new DecisionRateLimitException('rate limit', 429),
    '500' => new DecisionTransientException('server', 500),
    '529' => new DecisionTransientException('overloaded', 529),
    'transport' => new DecisionTransientException('network'),
]);

it('does not retry ordinary 4xx decode or unrelated failures', function (Throwable $error) {
    $driver = new DecisionLifecycleFakeDriver($error, decisionLifecycleResponse(1.0));
    $delay = new DecisionLifecycleFakeDelay;
    $pending = decisionLifecycleRuntime($driver, $delay)->create(decisionLifecycleRequest(
        retryPolicy: new DecisionRetryPolicy(maxAttempts: 3, baseDelayMs: 10, jitter: 'none'),
    ));

    expect(fn () => $pending->response())->toThrow($error::class)
        ->and($driver->calls)->toBe(1)
        ->and($delay->delays)->toBe([]);
})->with([
    'invalid request' => new DecisionInvalidRequestException('bad input', 422),
    'invalid response' => new DecisionResponseException('bad response'),
    'unrelated exception' => new RuntimeException('bug'),
]);

it('bounds attempts and delays and memoizes an exhausted transient failure', function () {
    $error = new DecisionTransientException('still unavailable', 529);
    $driver = new DecisionLifecycleFakeDriver($error);
    $delay = new DecisionLifecycleFakeDelay;
    $pending = decisionLifecycleRuntime($driver, $delay)->create(decisionLifecycleRequest(
        retryPolicy: new DecisionRetryPolicy(
            maxAttempts: 3,
            baseDelayMs: 10,
            maxDelayMs: 100,
            jitter: 'none',
        ),
    ));

    expect(fn () => $pending->response())->toThrow(DecisionTransientException::class)
        ->and(fn () => $pending->response())->toThrow(DecisionTransientException::class)
        ->and($driver->calls)->toBe(3)
        ->and($delay->delays)->toBe([10, 20]);
});

it('keeps execution and current attempt identities stable for lifecycle consumers', function () {
    $driver = new DecisionLifecycleFakeDriver(
        new DecisionTransientException('retry', 529),
        decisionLifecycleResponse(0.75),
    );
    $session = new DecisionExecutionSession(
        request: decisionLifecycleRequest(retryPolicy: new DecisionRetryPolicy(
            maxAttempts: 2,
            baseDelayMs: 0,
            jitter: 'none',
        )),
        driver: $driver,
        retryDelay: new DecisionLifecycleFakeDelay,
    );
    $executionId = $session->executionId();

    expect($session->attemptId())->toBeNull()
        ->and($session->attemptNumber())->toBe(0)
        ->and($session->response()->answers()->noul('safe')->probability())->toBe(0.75)
        ->and($session->attemptNumber())->toBe(2)
        ->and($session->attemptId())->toMatch('/^[0-9a-f-]{36}$/')
        ->and($session->executionId())->toBe($executionId)
        ->and($session->response())->toBe($session->response());
});

it('does not install ambient HTTP retry middleware for the implicit Decision client', function () {
    $ambient = new class implements HttpMiddleware
    {
        #[Override]
        public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse
        {
            return $next->handle($request);
        }
    };
    $classes = [];
    $fake = new DecisionLifecycleFakeDriver(decisionLifecycleResponse(1.0));
    $drivers = DecisionDriverRegistry::make()->withDriver(
        'test',
        static function ($config, $httpClient) use (&$classes, $fake): CanProcessDecisionRequest {
            if ($httpClient instanceof HttpClient) {
                $classes = array_column($httpClient->runtime()->middlewareStack()->toDebugArray(), 'class');
            }

            return $fake;
        },
    );
    HttpClientDefaults::withMiddleware($ambient);

    try {
        DecisionRuntime::fromConfig(
            new DecisionConfig(driver: 'test', model: 'jev-test'),
            drivers: $drivers,
        );
    } finally {
        HttpClientDefaults::clear();
    }

    expect($classes)->not->toContain($ambient::class);
});

final class DecisionLifecycleFakeDriver implements CanProcessDecisionRequest
{
    public int $calls = 0;

    /** @var non-empty-list<DecisionResponse|Throwable> */
    private array $outcomes;

    private DecisionResponse|Throwable $last;

    public function __construct(DecisionResponse|Throwable ...$outcomes)
    {
        if ($outcomes === []) {
            throw new InvalidArgumentException('Fake Decision driver requires an outcome.');
        }
        $this->outcomes = array_values($outcomes);
        $this->last = $this->outcomes[array_key_last($this->outcomes)];
    }

    #[Override]
    public function handle(DecisionRequest $request): DecisionResponse
    {
        $this->calls++;
        $outcome = array_shift($this->outcomes) ?? $this->last;
        if ($outcome instanceof Throwable) {
            throw $outcome;
        }

        return $outcome;
    }
}

final class DecisionLifecycleFakeDelay implements CanDelayRetries
{
    /** @var list<int> */
    public array $delays = [];

    #[Override]
    public function delay(int $milliseconds): void
    {
        $this->delays[] = $milliseconds;
    }
}

function decisionLifecycleRuntime(
    DecisionLifecycleFakeDriver $driver,
    ?DecisionLifecycleFakeDelay $delay = null,
): DecisionRuntime {
    return new DecisionRuntime(
        driver: $driver,
        events: new EventDispatcher,
        defaultModel: 'jev-default',
        retryDelay: $delay ?? new DecisionLifecycleFakeDelay,
    );
}

function decisionLifecycleRequest(
    ?string $model = null,
    ?DecisionRetryPolicy $retryPolicy = null,
): DecisionRequest {
    return new DecisionRequest(
        input: 'state',
        questions: decisionLifecycleQuestions(),
        model: $model,
        retryPolicy: $retryPolicy,
    );
}

function decisionLifecycleQuestions(): Questions
{
    return Questions::of(new Noul('safe'));
}

function decisionLifecycleResponse(float $probability): DecisionResponse
{
    return new DecisionResponse(
        answers: Answers::of(new NoulAnswer('safe', $probability)),
        model: 'jev-result',
    );
}
