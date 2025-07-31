<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Middleware;

use Cognesy\Pipeline\Computation;
use Cognesy\Pipeline\Tag\TagInterface;

/**
 * Middleware that adds tags to the computation during processing.
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

    public function handle(Computation $computation, callable $next): Computation {
        // Add tags to computation before processing
        $computationWithTags = empty($this->tags)
            ? $computation
            : $computation->with(...$this->tags);

        return $next($computationWithTags);
    }
}