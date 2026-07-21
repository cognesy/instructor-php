<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Middleware\RecordReplay;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Extras\Support\RecordReplay\CassetteStore;
use Cognesy\Http\Extras\Support\RecordReplay\FilesystemCassetteStore;
use Cognesy\Http\Extras\Support\RecordReplay\LegacyCassetteStore;
use Cognesy\Http\Extras\Support\RecordReplay\RecordReplayPolicy;
use Cognesy\Http\Extras\Support\RecordReplay\ReplayMissPolicy;
use Cognesy\Http\Extras\Support\RecordReplay\Events\HttpInteractionNotFound;
use Cognesy\Http\Extras\Support\RecordReplay\Events\HttpInteractionReplayed;
use Cognesy\Http\Extras\Support\RecordReplay\Events\HttpInteractionFallback;
use Cognesy\Http\Extras\Support\RecordReplay\Events\HttpInteractionMismatch;
use Cognesy\Http\Extras\Support\RecordReplay\Events\HttpInteractionExhausted;
use Cognesy\Http\Extras\Support\RecordReplay\Events\HttpInteractionCorrupt;
use Cognesy\Http\Extras\Support\RecordReplay\Exceptions\CassetteCorruptException;
use Cognesy\Http\Extras\Support\RecordReplay\Exceptions\CassetteExhaustedException;
use Cognesy\Http\Extras\Support\RecordReplay\Exceptions\CassetteMismatchException;
use Cognesy\Http\Extras\Support\RecordReplay\Exceptions\UnsupportedCassetteVersionException;
use Cognesy\Http\Extras\Support\RecordReplay\Events\HttpInteractionSummary;
use Cognesy\Http\Extras\Support\RecordReplay\Exceptions\RecordingNotFoundException;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Immutable HTTP record/replay middleware.
 *
 * Use {@see self::recordTo()} or {@see self::replayFrom()} for filesystem
 * cassettes. {@see self::recordWith()} and {@see self::replayWith()} are the
 * extension seam for non-filesystem stores.
 */
final class RecordReplayMiddleware implements HttpMiddleware
{
    /** @deprecated Use recordTo(), replayFrom(), or omit the middleware. */
    public const MODE_PASS = 'pass';

    /** @deprecated Use recordTo(). */
    public const MODE_RECORD = 'record';

    /** @deprecated Use replayFrom(). */
    public const MODE_REPLAY = 'replay';

    private function __construct(
        private readonly bool $recording,
        private readonly CassetteStore $store,
        private readonly RecordReplayPolicy $policy,
        private readonly EventDispatcherInterface $events,
    ) {
    }

    public static function recordTo(
        string $directory,
        ?RecordReplayPolicy $policy = null,
        ?EventDispatcherInterface $events = null,
    ): self {
        $policy ??= new RecordReplayPolicy();

        return self::recordWith(self::storeForDirectory($directory, $policy), $policy, $events);
    }

    public static function replayFrom(
        string $directory,
        ?RecordReplayPolicy $policy = null,
        ?EventDispatcherInterface $events = null,
    ): self {
        $policy ??= new RecordReplayPolicy();

        return self::replayWith(self::storeForDirectory($directory, $policy), $policy, $events);
    }

    public static function recordWith(
        CassetteStore $store,
        ?RecordReplayPolicy $policy = null,
        ?EventDispatcherInterface $events = null,
    ): self {
        return new self(
            recording: true,
            store: $store,
            policy: $policy ?? new RecordReplayPolicy(),
            events: $events ?? new EventDispatcher(),
        );
    }

    public static function replayWith(
        CassetteStore $store,
        ?RecordReplayPolicy $policy = null,
        ?EventDispatcherInterface $events = null,
    ): self {
        return new self(
            recording: false,
            store: $store,
            policy: $policy ?? new RecordReplayPolicy(),
            events: $events ?? new EventDispatcher(),
        );
    }

    #[\Override]
    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse
    {
        if ($this->recording) {
            return $this->store->record($request, $next->handle($request));
        }

        try {
            $response = $this->store->replay($request);
        } catch (CassetteMismatchException $exception) {
            $this->events->dispatch(new HttpInteractionMismatch(
                HttpInteractionSummary::fromRequest($request, 'mismatch'),
            ));
            throw $exception;
        } catch (CassetteExhaustedException $exception) {
            $this->events->dispatch(new HttpInteractionExhausted(
                HttpInteractionSummary::fromRequest($request, 'exhausted'),
            ));
            throw $exception;
        } catch (CassetteCorruptException|UnsupportedCassetteVersionException $exception) {
            $this->events->dispatch(new HttpInteractionCorrupt(
                HttpInteractionSummary::fromRequest($request, 'corrupt'),
            ));
            throw $exception;
        }
        if ($response !== null) {
            $this->events->dispatch(new HttpInteractionReplayed(
                HttpInteractionSummary::fromRequest($request, 'replayed', $response->statusCode()),
            ));
            return $response;
        }

        if ($this->policy->onMissing === ReplayMissPolicy::Passthrough) {
            $this->events->dispatch(new HttpInteractionFallback(
                HttpInteractionSummary::fromRequest($request, 'fallback'),
            ));
            return $next->handle($request);
        }

        $this->events->dispatch(new HttpInteractionNotFound(
            HttpInteractionSummary::fromRequest($request, 'not_found'),
        ));
        throw new RecordingNotFoundException(
            "No recording found for {$request->method()} request.",
        );
    }

    private static function storeForDirectory(string $directory, RecordReplayPolicy $policy): CassetteStore
    {
        try {
            return FilesystemCassetteStore::fromDirectory(
                directory: $directory,
                sanitizer: $policy->sanitizer,
                matcher: $policy->matcher,
            );
        } catch (\Cognesy\Http\Extras\Support\RecordReplay\Exceptions\LegacyCassetteException) {
            return LegacyCassetteStore::fromDirectory(
                directory: $directory,
                redactor: $policy->sanitizer,
                matcher: $policy->matcher,
            );
        }
    }
}
