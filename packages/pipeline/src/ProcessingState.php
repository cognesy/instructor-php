<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Cognesy\Pipeline\StateContracts\CanCarryState;
use Cognesy\Utils\Result\Result;
use Cognesy\Utils\TagMap\Contracts\TagInterface;
use Cognesy\Utils\TagMap\Contracts\TagMapInterface;

/**
 * ProcessingState is an immutable object containing current processing state.
 *
 * It consists of:
 *  - output - value (payload) wrapped in Result for uniform handling of Success and Failure states
 *  - tags - metadata objects for cross-cutting concerns
 */
final readonly class ProcessingState implements CanCarryState
{
    use Traits\HandlesTags;
    use Traits\HandlesResult;

    public function __construct(
        Result $result,
        TagMapInterface $tags,
    ) {
        $this->result = $result;
        $this->tags = $tags;
    }

    // CONSTRUCTORS

    public static function empty(): static {
        return new self(
            result: Result::success(null),
            tags: self::defaultTagMap(),
        );
    }

    /**
     * @param mixed $value The value to wrap in a ProcessingState
     * @param array<TagInterface> $tags Optional tags to associate with this state
     */
    public static function with(mixed $value, array $tags = []): static {
        return new self(
            result: Result::from($value),
            tags: self::defaultTagMap($tags),
        );
    }

    // QUERY AND TRANSFORMATION APIs

    #[\Override]
    public function applyTo(CanCarryState $priorState): CanCarryState {
        $newState = $this->replaceTags(
            ...$priorState->tagMap()->merge($this->tagMap())->getAllInOrder()
        );
        return new self($newState->result(), $newState->tagMap());
    }

    public function transform() : TransformState {
        return new TransformState($this);
    }
}