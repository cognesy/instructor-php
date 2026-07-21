<?php declare(strict_types=1);

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Extras\Middleware\RecordReplay\RecordReplayMiddleware;
use Cognesy\Http\Extras\Middleware\RecordReplay\RecordingMiddleware;
use Cognesy\Http\Extras\Support\RecordReplay\CassetteStore;
use Cognesy\Http\Extras\Support\RecordReplay\Events\HttpInteractionFallback;
use Cognesy\Http\Extras\Support\RecordReplay\Events\HttpInteractionExhausted;
use Cognesy\Http\Extras\Support\RecordReplay\Events\HttpInteractionNotFound;
use Cognesy\Http\Extras\Support\RecordReplay\Events\HttpInteractionRecorded;
use Cognesy\Http\Extras\Support\RecordReplay\FixtureSanitizer;
use Cognesy\Http\Extras\Support\RecordReplay\Matching\RequestMatcher;
use Cognesy\Http\Extras\Support\RecordReplay\Matching\LegacyExactHashMatcher;
use Cognesy\Http\Extras\Support\RecordReplay\RequestRecords;
use Cognesy\Http\Extras\Support\RecordReplay\RecordReplayPolicy;
use Cognesy\Http\Extras\Support\RecordReplay\ReplayMissPolicy;
use Cognesy\Http\Extras\Support\RecordReplay\Exceptions\RecordingNotFoundException;
use Cognesy\Http\Extras\Support\RecordReplay\Exceptions\CassetteExhaustedException;

test('the public middleware is final and exposes immutable named constructors', function () {
    $reflection = new ReflectionClass(RecordReplayMiddleware::class);

    expect($reflection->isFinal())->toBeTrue()
        ->and($reflection->hasMethod('recordTo'))->toBeTrue()
        ->and($reflection->hasMethod('replayFrom'))->toBeTrue()
        ->and($reflection->hasMethod('recordWith'))->toBeTrue()
        ->and($reflection->hasMethod('replayWith'))->toBeTrue()
        ->and($reflection->hasMethod('setMode'))->toBeFalse()
        ->and($reflection->hasMethod('setStorageDir'))->toBeFalse()
        ->and($reflection->hasMethod('setFallbackToRealRequests'))->toBeFalse()
        ->and($reflection->hasMethod('getRecords'))->toBeFalse();
});

test('custom policy and cassette store are injected through the public API', function () {
    $seen = (object) ['recorded' => false, 'matched' => false];
    $store = new class($seen) implements CassetteStore {
        public function __construct(private object $seen) {}

        public function record(HttpRequest $request, HttpResponse $response): HttpResponse {
            $this->seen->recorded = true;
            return $response;
        }

        public function replay(HttpRequest $request): ?HttpResponse {
            $this->seen->matched = true;
            return HttpResponse::sync(200, [], '{"replayed":true}');
        }
    };

    $matcher = new class implements RequestMatcher {
        public function fingerprint(HttpRequest $request): string {
            return 'test-fingerprint';
        }
    };
    $sanitizer = new class implements FixtureSanitizer {
        public function redact(array $requestData): array {
            return $requestData;
        }

        public function redactResponse(array $responseData): array {
            return $responseData;
        }

        public function redactStream(iterable $chunks): iterable {
            yield from $chunks;
        }
    };
    $policy = new RecordReplayPolicy($matcher, $sanitizer);
    $request = new HttpRequest('https://example.test/items', 'GET', [], '', []);
    $next = new class implements CanHandleHttpRequest {
        public function handle(HttpRequest $request): HttpResponse {
            return HttpResponse::sync(201, [], '{"created":true}');
        }
    };

    $recording = RecordReplayMiddleware::recordWith($store, $policy);
    $recording->handle($request, $next);

    $replaying = RecordReplayMiddleware::replayWith($store, $policy);
    $response = $replaying->handle($request, $next);

    expect($seen->recorded)->toBeTrue()
        ->and($seen->matched)->toBeTrue()
        ->and($response->statusCode())->toBe(200);
});

test('custom sanitizer injection is exercised through the public middleware', function () {
    $directory = sys_get_temp_dir() . '/http_record_replay_sanitizer_' . uniqid('', true);
    $calls = (object) ['request' => 0, 'response' => 0, 'stream' => 0];
    $sanitizer = new class($calls) implements FixtureSanitizer {
        public function __construct(private object $calls) {}

        public function redact(array $requestData): array {
            $this->calls->request++;
            $requestData['url'] = 'https://sanitized.test/request';
            return $requestData;
        }

        public function redactResponse(array $responseData): array {
            $this->calls->response++;
            $responseData['body'] = 'sanitized-response';
            return $responseData;
        }

        public function redactStream(iterable $chunks): iterable {
            $this->calls->stream++;
            yield from $chunks;
        }
    };
    $policy = new RecordReplayPolicy(sanitizer: $sanitizer);
    $request = new HttpRequest('https://example.test/secret?token=url-secret', 'GET', [], '', []);
    $next = new class implements CanHandleHttpRequest {
        public function handle(HttpRequest $request): HttpResponse {
            return HttpResponse::sync(200, [], 'live-response');
        }
    };

    try {
        RecordReplayMiddleware::recordTo($directory, $policy)->handle($request, $next);
        $interaction = $directory . '/interactions/000001/interaction.json';
        $contents = file_get_contents($interaction) ?: '';
        $responseBody = file_get_contents($directory . '/interactions/000001/response.body') ?: '';

        expect($calls->request)->toBe(1)
            ->and($calls->response)->toBe(1)
            ->and($responseBody)->toContain('sanitized-response')
            ->and($contents)->toContain('sanitized.test');
    } finally {
        $remove = function (string $path) use (&$remove): void {
            if (!is_dir($path)) {
                return;
            }
            foreach (scandir($path) ?: [] as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $child = $path . '/' . $name;
                if (is_dir($child)) {
                    $remove($child);
                    continue;
                }
                unlink($child);
            }
            rmdir($path);
        };
        $remove($directory);
    }
});

test('custom matcher injection works through recordTo and replayFrom', function () {
    $directory = sys_get_temp_dir() . '/http_record_replay_matcher_' . uniqid('', true);
    $matcher = new class implements \Cognesy\Http\Extras\Support\RecordReplay\Matching\RequestMatcher {
        public function fingerprint(HttpRequest $request): string {
            return 'same-scenario';
        }
    };
    $policy = new RecordReplayPolicy(matcher: $matcher);
    $recordedRequest = new HttpRequest('https://example.test/first', 'GET', [], '', []);
    $replayRequest = new HttpRequest('https://example.test/second', 'POST', [], 'different', []);

    try {
        RecordReplayMiddleware::recordTo($directory, $policy)->handle(
            $recordedRequest,
            new class implements CanHandleHttpRequest {
                public function handle(HttpRequest $request): HttpResponse {
                    return HttpResponse::sync(200, [], 'matched-by-custom-policy');
                }
            },
        );
        $response = RecordReplayMiddleware::replayFrom($directory, $policy)->handle(
            $replayRequest,
            new class implements CanHandleHttpRequest {
                public function handle(HttpRequest $request): HttpResponse {
                    throw new RuntimeException('custom matcher replay should not call next');
                }
            },
        );

        expect($response->body())->toBe('matched-by-custom-policy');
    } finally {
        $remove = function (string $path) use (&$remove): void {
            if (!is_dir($path)) {
                return;
            }
            foreach (scandir($path) ?: [] as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $child = $path . '/' . $name;
                if (is_dir($child)) {
                    $remove($child);
                    continue;
                }
                unlink($child);
            }
            rmdir($path);
        };
        $remove($directory);
    }
});

test('replay is hermetic by default and passthrough is explicit', function () {
    $store = new class implements CassetteStore {
        public function record(HttpRequest $request, HttpResponse $response): HttpResponse {
            return $response;
        }

        public function replay(HttpRequest $request): ?HttpResponse {
            return null;
        }
    };
    $request = new HttpRequest('https://example.test/missing', 'GET', [], '', []);
    $calls = (object) ['count' => 0];
    $next = new class($calls) implements CanHandleHttpRequest {
        public function __construct(private object $calls) {}

        public function handle(HttpRequest $request): HttpResponse {
            $this->calls->count++;
            return HttpResponse::sync(204, [], '');
        }
    };

    expect(fn () => RecordReplayMiddleware::replayWith($store)->handle($request, $next))
        ->toThrow(RecordingNotFoundException::class);
    expect($calls->count)->toBe(0);

    $response = RecordReplayMiddleware::replayWith(
        $store,
        new RecordReplayPolicy(onMissing: ReplayMissPolicy::Passthrough),
    )->handle($request, $next);

    expect($response->statusCode())->toBe(204)
        ->and($calls->count)->toBe(1);
});

test('replay events and miss exceptions expose only sanitized summaries', function () {
    $store = new class implements CassetteStore {
        public function record(HttpRequest $request, HttpResponse $response): HttpResponse {
            return $response;
        }

        public function replay(HttpRequest $request): ?HttpResponse {
            return null;
        }
    };
    $events = new \Cognesy\Events\Dispatchers\EventDispatcher();
    $captured = [];
    $events->wiretap(static function (object $event) use (&$captured): void {
        $captured[] = $event;
    });
    $request = new HttpRequest(
        'https://example.test/items?token=url-secret',
        'GET',
        ['Authorization' => 'Bearer header-secret'],
        '{"password":"body-secret"}',
        [],
    );

    try {
        RecordReplayMiddleware::replayWith($store, events: $events)->handle(
            $request,
            new class implements CanHandleHttpRequest {
                public function handle(HttpRequest $request): HttpResponse {
                    throw new RuntimeException('must not be called');
                }
            },
        );
    } catch (RecordingNotFoundException $exception) {
        expect($exception->getMessage())
            ->not->toContain('url-secret')
            ->not->toContain('body-secret');
    }

    expect($captured[0])->toBeInstanceOf(HttpInteractionNotFound::class)
        ->and($captured[0]->interaction->url)->not->toContain('url-secret')
        ->and(json_encode($captured[0]))->not->toContain('header-secret')
        ->and(json_encode($captured[0]))->not->toContain('body-secret')
        ->and(get_object_vars($captured[0]))->not->toHaveKey('request')
        ->and(get_object_vars($captured[0]))->not->toHaveKey('response');

    $captured = [];
    $response = RecordReplayMiddleware::replayWith(
        $store,
        new RecordReplayPolicy(onMissing: ReplayMissPolicy::Passthrough),
        $events,
    )->handle(
        $request,
        new class implements CanHandleHttpRequest {
            public function handle(HttpRequest $request): HttpResponse {
                return HttpResponse::sync(204, [], '');
            }
        },
    );

    expect($response->statusCode())->toBe(204)
        ->and($captured[0])->toBeInstanceOf(HttpInteractionFallback::class)
        ->and(json_encode($captured[0]))->not->toContain('url-secret');
});

test('recorded events contain no raw request or response payloads', function () {
    $directory = sys_get_temp_dir() . '/http_record_replay_events_' . uniqid('', true);
    $events = new \Cognesy\Events\Dispatchers\EventDispatcher();
    $captured = [];
    $events->wiretap(static function (object $event) use (&$captured): void {
        $captured[] = $event;
    });
    $request = new HttpRequest(
        'https://example.test/items?key=url-secret',
        'POST',
        ['Authorization' => 'Bearer header-secret'],
        '{"prompt":"body-secret"}',
        [],
    );

    try {
        (new RecordingMiddleware($directory, $events))->handle(
            $request,
            new class implements CanHandleHttpRequest {
                public function handle(HttpRequest $request): HttpResponse {
                    return HttpResponse::sync(201, [], '{"output":"response-secret"}');
                }
            },
        );

        expect($captured[0])->toBeInstanceOf(HttpInteractionRecorded::class)
            ->and(json_encode($captured[0]))->not->toContain('url-secret')
            ->and(json_encode($captured[0]))->not->toContain('header-secret')
            ->and(json_encode($captured[0]))->not->toContain('body-secret')
            ->and(json_encode($captured[0]))->not->toContain('response-secret');
    } finally {
        foreach (glob($directory . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
});

test('ordered replay exhaustion is typed, hermetic, and safely observable', function () {
    $directory = sys_get_temp_dir() . '/http_record_replay_order_' . uniqid('', true);
    $request = new HttpRequest('https://example.test/items?token=url-secret', 'GET', [], '', []);
    $recording = RecordReplayMiddleware::recordTo($directory);
    $responses = ['first', 'second'];
    $state = (object) ['index' => 0];
    $next = new class($responses, $state) implements CanHandleHttpRequest {
        public function __construct(private array $responses, private object $state) {}

        public function handle(HttpRequest $request): HttpResponse {
            return HttpResponse::sync(200, [], $this->responses[$this->state->index++]);
        }
    };

    try {
        $recording->handle($request, $next);
        $recording->handle($request, $next);

        $events = new \Cognesy\Events\Dispatchers\EventDispatcher();
        $captured = [];
        $events->wiretap(static function (object $event) use (&$captured): void {
            $captured[] = $event;
        });
        $replayed = RecordReplayMiddleware::replayFrom($directory, events: $events);
        $downstreamState = (object) ['calls' => 0];
        $downstream = new class($downstreamState) implements CanHandleHttpRequest {
            public function __construct(private object $state) {}

            public function handle(HttpRequest $request): HttpResponse {
                $this->state->calls++;
                return HttpResponse::sync(500, [], 'must not reach network');
            }
        };

        expect($replayed->handle($request, $downstream)->body())->toBe('first')
            ->and($replayed->handle($request, $downstream)->body())->toBe('second');
        expect(fn () => $replayed->handle($request, $downstream))
            ->toThrow(CassetteExhaustedException::class);
        expect($downstreamState->calls)->toBe(0)
            ->and($captured[2])->toBeInstanceOf(HttpInteractionExhausted::class)
            ->and(json_encode($captured[2]))->not->toContain('url-secret');
    } finally {
        $remove = function (string $path) use (&$remove): void {
            if (!is_dir($path)) {
                return;
            }
            foreach (scandir($path) ?: [] as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $child = $path . '/' . $name;
                if (is_dir($child)) {
                    $remove($child);
                    continue;
                }
                unlink($child);
            }
            rmdir($path);
        };
        $remove($directory);
    }
});

test('old single-file fixtures use the isolated compatibility reader', function () {
    $directory = sys_get_temp_dir() . '/http_record_replay_legacy_' . uniqid('', true);
    $request = new HttpRequest('https://example.test/legacy', 'GET', [], '', []);

    try {
        (new RequestRecords($directory, new LegacyExactHashMatcher()))
            ->save($request, HttpResponse::sync(200, [], 'legacy-response'));

        $response = RecordReplayMiddleware::replayFrom($directory)->handle(
            $request,
            new class implements CanHandleHttpRequest {
                public function handle(HttpRequest $request): HttpResponse {
                    throw new RuntimeException('legacy replay should not call next');
                }
            },
        );

        expect($response->body())->toBe('legacy-response');
    } finally {
        $remove = function (string $path) use (&$remove): void {
            if (!is_dir($path)) {
                return;
            }
            foreach (scandir($path) ?: [] as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $child = $path . '/' . $name;
                if (is_dir($child)) {
                    $remove($child);
                    continue;
                }
                unlink($child);
            }
            rmdir($path);
        };
        $remove($directory);
    }
});
