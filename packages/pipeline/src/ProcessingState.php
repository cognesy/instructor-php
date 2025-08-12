<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Cognesy\Pipeline\Contracts\CanCarryState;
use Cognesy\Pipeline\Contracts\TagInterface;
use Cognesy\Pipeline\Contracts\TagMapInterface;
use Cognesy\Pipeline\Internal\IndexedTagMap;
use Cognesy\Pipeline\Tag\ErrorTag;
use Cognesy\Pipeline\Tag\TagQuery;
use Cognesy\Utils\Result\Result;
use RuntimeException;
use Throwable;

/**
 * ProcessingState is an immutable object containing current processing state.
 *
 * It consists of:
 *  - output - value (payload) wrapped in Result for uniform handling of Success and Failure states
 *  - tags - metadata objects for cross-cutting concerns
 */
final readonly class ProcessingState implements CanCarryState
{
    private Result $result;
    private TagMapInterface $tags;

    public function __construct(
        Result $result,
        TagMapInterface $tags,
    ) {
        $this->result = $result;
        $this->tags = $tags;
    }

    // CONSTRUCTORS

    public static function empty(): self {
        return new self(
            result: Result::success(null),
            tags: self::defaultTagMap(),
        );
    }

    /**
     * @param mixed $value The value to wrap in a ProcessingState
     * @param array<TagInterface> $tags Optional tags to associate with this state
     */
    public static function with(mixed $value, array $tags = []): self {
        return new self(
            result: Result::from($value),
            tags: self::defaultTagMap($tags),
        );
    }

    public function withResult(Result $result): self {
        return new self($result, $this->tags);
    }

    public function addTags(TagInterface ...$tags): self {
        return new self($this->result, $this->tags->add(...$tags));
    }

    public function replaceTags(TagInterface ...$tags): self {
        return new self($this->result, $this->tags->replace(...$tags));
    }

    public function failWith(string|Throwable $cause): self {
        $message = $cause instanceof Throwable ? $cause->getMessage() : $cause;
        $exception = match (true) {
            is_string($cause) => new RuntimeException($cause),
            $cause instanceof Throwable => $cause,
        };
        return $this
            ->withResult(Result::failure($exception))
            ->addTags(new ErrorTag(error: $message));
    }

    // ACCESSORS - RESULT

    public function result(): Result {
        return $this->result;
    }

    public function value(): mixed {
        if ($this->result->isFailure()) {
            throw new RuntimeException('Cannot unwrap value from a failed result');
        }
        return $this->result->unwrap();
    }

    public function valueOr(mixed $default): mixed {
        return $this->result->valueOr($default);
    }

    public function isSuccess(): bool {
        return $this->result->isSuccess();
    }

    public function isFailure(): bool {
        return $this->result->isFailure();
    }

    public function exception(): Throwable {
        if ($this->result->isSuccess()) {
            throw new RuntimeException('Cannot get exception from a successful result');
        }
        return $this->result->exception();
    }

    public function exceptionOr(mixed $default): mixed {
        return $this->result->exceptionOr($default);
    }

    // ACCESSORS - TAGS

    public function tagMap(): TagMapInterface {
        return $this->tags;
    }

    /**
     * Get all tags, optionally filtered by class.
     *
     * @param class-string|null $tagClass Optional class filter
     * @return TagInterface[]
     */
    public function allTags(?string $tagClass = null): array {
        return $this->tags->query()->only($tagClass)->all();
    }

    /**
     * @param class-string $tagClass
     */
    public function hasTag(string $tagClass): bool {
        return $this->tags->has($tagClass);
    }

    // QUERY AND TRANSFORMATION APIs

    public function tags(): TagQuery {
        return $this->tagMap()->query();
    }

    public function transform() : TransformState {
        return new TransformState($this);
    }

    // PRIVATE

    private static function defaultTagMap(?array $tags = []): TagMapInterface {
        return match(true) {
            $tags === null => IndexedTagMap::empty(),
            default => IndexedTagMap::create($tags),
        };
    }
}