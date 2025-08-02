<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware;

use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\Tag\TagInterface;

/**
 * Middleware that adds tags to the state during processing.
 *
 * This allows for deferred tag addition without storing tags on the pipeline instance,
 * maintaining the pure computation approach while providing good developer experience.
 */
class AddTagsMiddleware implements PipelineMiddlewareInterface
{
    /** @var TagInterface[] */
    private array $tags;

    public function __construct(TagInterface ...$tags) {
        $this->tags = $tags;
    }

    /**
     * Factory method for fluent API.
     */
    public static function with(TagInterface ...$tags): self {
        return new self(...$tags);
    }

    public function handle(ProcessingState $state, callable $next): ProcessingState {
        // Add tags to state before processing
        $stateWithTags = empty($this->tags)
            ? $state
            : $state->withTags(...$this->tags);

        return $next($stateWithTags);
    }
}