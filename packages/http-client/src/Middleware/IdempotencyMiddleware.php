<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Utils\Uuid;

final class IdempotencyMiddleware implements HttpMiddleware
{
    /** @var (\Closure(HttpRequest): string)|null */
    private readonly ?\Closure $keyProvider;

    /**
     * @param list<string> $methods
     * @param list<string>|null $hostAllowList
     * @param (callable(HttpRequest): string)|null $keyProvider
     */
    public function __construct(
        private readonly string $headerName = 'Idempotency-Key',
        private readonly array $methods = ['POST'],
        private readonly ?array $hostAllowList = null,
        ?callable $keyProvider = null,
    ) {
        $this->keyProvider = $keyProvider !== null ? \Closure::fromCallable($keyProvider) : null;
    }

    #[\Override]
    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse {
        if ($this->shouldAttach($request)) {
            $key = $this->makeKey($request);
            $request = $request->withHeader($this->headerName, $key);
        }

        return $next->handle($request);
    }

    private function shouldAttach(HttpRequest $request): bool {
        if (!in_array(strtoupper($request->method()), array_map('strtoupper', $this->methods), true)) {
            return false;
        }

        if ($this->hostAllowList !== null) {
            $host = parse_url($request->url(), PHP_URL_HOST) ?: '';
            if (!in_array($host, $this->hostAllowList, true)) {
                return false;
            }
        }

        $existing = $request->headers($this->headerName);
        if (!empty($existing)) {
            return false;
        }

        return true;
    }

    private function makeKey(HttpRequest $request): string {
        if ($this->keyProvider !== null) {
            return (string) call_user_func($this->keyProvider, $request);
        }
        return Uuid::uuid4();
    }
}
