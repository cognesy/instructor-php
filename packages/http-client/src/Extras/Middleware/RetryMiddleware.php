<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Middleware;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\Extras\Support\RetryPolicy;

final class RetryMiddleware implements HttpMiddleware
{
    public function __construct(
        private readonly RetryPolicy $policy = new RetryPolicy(),
    ) {}

    #[\Override]
    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse {
        if ($request->isStreamed() || !$this->policy->canRetryRequest($request)) {
            return $next->handle($request);
        }

        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                $response = $next->handle($request);
            } catch (\Throwable $error) {
                if ($this->policy->shouldRetryException($error, $attempt)) {
                    $response = $error instanceof HttpRequestException
                        ? $error->getResponse()
                        : null;
                    $this->sleepFor($this->policy->delayMsForAttempt($attempt, $response));
                    continue;
                }
                throw $error;
            }

            if ($this->policy->shouldRetryResponse($response, $attempt)) {
                $this->sleepFor($this->policy->delayMsForAttempt($attempt, $response));
                continue;
            }

            return $response;
        }
    }

    private function sleepFor(int $delayMs): void {
        if ($delayMs <= 0) {
            return;
        }
        usleep($delayMs * 1000);
    }
}
