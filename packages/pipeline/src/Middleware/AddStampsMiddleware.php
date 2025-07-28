<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware;

use Cognesy\Pipeline\Envelope;
use Cognesy\Pipeline\PipelineMiddlewareInterface;
use Cognesy\Pipeline\StampInterface;

/**
 * Middleware that adds stamps to the envelope during processing.
 *
 * This allows for deferred stamp addition without storing stamps on the pipeline instance,
 * maintaining the pure envelope approach while providing good developer experience.
 */
class AddStampsMiddleware implements PipelineMiddlewareInterface
{
    /** @var StampInterface[] */
    private array $stamps;

    public function __construct(StampInterface ...$stamps) {
        $this->stamps = $stamps;
    }

    /**
     * Factory method for fluent API.
     */
    public static function with(StampInterface ...$stamps): self {
        return new self(...$stamps);
    }

    public function handle(Envelope $envelope, callable $next): Envelope {
        // Add stamps to envelope before processing
        $envelopeWithStamps = empty($this->stamps)
            ? $envelope
            : $envelope->with(...$this->stamps);

        return $next($envelopeWithStamps);
    }
}