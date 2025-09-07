<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Mock;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;

/**
 * Fluent expectation builder for MockHttpDriver.
 */
class MockExpectation
{
    /** @var callable[] */
    private array $matchers = [];
    /** @var int|null Times this expectation can be used; null means unlimited */
    private ?int $times = null;
    private ?bool $requireStream = null;

    public function __construct(private readonly MockHttpDriver $driver) {}

    // Matchers ///////////////////////////////////////////////////////////

    public function method(string $method): self {
        $method = strtoupper($method);
        $this->matchers[] = function (HttpRequest $r) use ($method): bool {
            return strtoupper($r->method()) === $method;
        };
        return $this;
    }

    public function get(string|callable|null $url = null): self {
        return $this->method('GET')->url($url);
    }

    public function post(string|callable|null $url = null): self {
        return $this->method('POST')->url($url);
    }

    public function put(string|callable|null $url = null): self {
        return $this->method('PUT')->url($url);
    }

    public function patch(string|callable|null $url = null): self {
        return $this->method('PATCH')->url($url);
    }

    public function delete(string|callable|null $url = null): self {
        return $this->method('DELETE')->url($url);
    }

    public function url(string|callable|null $matcher): self {
        if ($matcher === null) {
            return $this;
        }
        if (is_string($matcher)) {
            $this->matchers[] = fn(HttpRequest $r): bool => $r->url() === $matcher;
        } elseif (is_callable($matcher)) {
            $this->matchers[] = fn(HttpRequest $r): bool => (bool)$matcher($r->url());
        }
        return $this;
    }

    public function urlStartsWith(string $prefix): self {
        $this->matchers[] = fn(HttpRequest $r): bool => str_starts_with($r->url(), $prefix);
        return $this;
    }

    public function urlMatches(string $pattern): self {
        $this->matchers[] = fn(HttpRequest $r): bool => 1 === preg_match($pattern, $r->url());
        return $this;
    }

    public function path(string $path): self {
        $this->matchers[] = function (HttpRequest $r) use ($path): bool {
            $p = parse_url($r->url(), PHP_URL_PATH) ?: '';
            return $p === $path;
        };
        return $this;
    }

    public function header(string $name, string|callable $value): self {
        $lname = strtolower($name);
        $this->matchers[] = function (HttpRequest $r) use ($lname, $value): bool {
            $headers = array_change_key_case($r->headers(), CASE_LOWER);
            if (!array_key_exists($lname, $headers)) {
                return false;
            }
            $v = $headers[$lname];
            return is_callable($value) ? (bool)$value($v) : $v === $value;
        };
        return $this;
    }

    public function headers(array $mustMatch): self {
        foreach ($mustMatch as $k => $v) {
            $this->header((string)$k, is_callable($v) ? $v : (string)$v);
        }
        return $this;
    }

    public function withStream(?bool $stream): self {
        $this->requireStream = $stream;
        $this->matchers[] = fn(HttpRequest $r): bool => $stream === null ? true : $r->isStreamed() === $stream;
        return $this;
    }

    public function bodyEquals(string $body): self {
        $this->matchers[] = fn(HttpRequest $r): bool => $r->body()->toString() === $body;
        return $this;
    }

    public function bodyContains(string $needle): self {
        $this->matchers[] = fn(HttpRequest $r): bool => str_contains($r->body()->toString(), $needle);
        return $this;
    }

    public function bodyMatchesRegex(string $pattern): self {
        $this->matchers[] = fn(HttpRequest $r): bool => 1 === preg_match($pattern, $r->body()->toString());
        return $this;
    }

    public function withJsonSubset(array $subset): self {
        $this->matchers[] = function (HttpRequest $r) use ($subset): bool {
            $raw = $r->body()->toString();
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return false;
            }
            return self::isAssocSubset($subset, $decoded);
        };
        return $this;
    }

    public function body(callable $predicate): self {
        $this->matchers[] = fn(HttpRequest $r): bool => (bool)$predicate($r->body()->toString());
        return $this;
    }

    public function times(int $times): self {
        $this->times = max(0, $times);
        return $this;
    }

    // Responses //////////////////////////////////////////////////////////

    public function reply(HttpResponse|callable $response): MockHttpDriver {
        $compiled = [
            'matchers' => $this->matchers,
            'times' => $this->times,
            'response' => $response,
        ];
        return $this->driver->registerExpectation($compiled);
    }

    public function replyJson(array|string|\JsonSerializable $data, int $status = 200, array $headers = []): MockHttpDriver {
        return $this->reply(MockHttpResponse::json($data, $status, $headers));
    }

    public function replyText(string $text, int $status = 200, array $headers = []): MockHttpDriver {
        return $this->reply(MockHttpResponse::success($status, $headers, $text));
    }

    public function replyStreamChunks(array $chunks, int $status = 200, array $headers = []): MockHttpDriver {
        return $this->reply(MockHttpResponse::streaming($status, $headers, $chunks));
    }

    public function replySSEFromJson(array $payloads, bool $addDone = true, int $status = 200, array $headers = []): MockHttpDriver {
        return $this->reply(MockHttpResponse::sse($payloads, $addDone, $status, $headers));
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    private static function isAssocSubset(array $subset, array $target): bool {
        foreach ($subset as $k => $v) {
            if (!array_key_exists($k, $target)) {
                return false;
            }
            if (is_array($v)) {
                if (!is_array($target[$k]) || !self::isAssocSubset($v, $target[$k])) {
                    return false;
                }
            } else {
                if ($target[$k] !== $v) {
                    return false;
                }
            }
        }
        return true;
    }
}

