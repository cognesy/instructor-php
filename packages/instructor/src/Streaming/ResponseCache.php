<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming;

use Cognesy\Instructor\Data\StructuredOutputResponse;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;

/**
 * Bounded retention of streamed responses for replay (opt-in via
 * ResponseCachePolicy). Retention is capped: a replay must be either complete
 * or explicitly unavailable — a partial replay would be silently wrong. When
 * the cap is exceeded the cache invalidates itself and replay availability
 * reverts to the uncached behavior (explicit error at the call site).
 */
final class ResponseCache
{
    public const MAX_CACHED_RESPONSES = 10_000;

    /** @var list<StructuredOutputResponse> */
    private array $responses = [];
    private bool $overflowed = false;

    public function __construct(
        private readonly ResponseCachePolicy $policy,
    ) {}

    public function remember(StructuredOutputResponse $response): void {
        if (!$this->policy->shouldCache() || $this->overflowed) {
            return;
        }

        if (count($this->responses) >= self::MAX_CACHED_RESPONSES) {
            $this->responses = [];
            $this->overflowed = true;
            return;
        }

        $this->responses[] = $response;
    }

    public function canReplay(): bool {
        return $this->policy->shouldCache() && !$this->overflowed;
    }

    /**
     * @return list<StructuredOutputResponse>
     */
    public function replay(): array {
        return $this->responses;
    }
}
