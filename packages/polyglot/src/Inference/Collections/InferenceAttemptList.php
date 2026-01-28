<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Collections;

use Cognesy\Polyglot\Inference\Data\InferenceAttempt;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Collection\ArrayList;
use InvalidArgumentException;

class InferenceAttemptList
{
    /** @var ArrayList<InferenceAttempt> */
    private ArrayList $attempts;

    // CONSTRUCTORS //////////////////////////////////////////

    private function __construct(?ArrayList $attempts = null) {
        $this->attempts = $attempts ?? ArrayList::empty();
    }

    public static function of(InferenceAttempt ...$attempts): self {
        return new self(ArrayList::of(...$attempts));
    }

    public static function empty(): self {
        return new self();
    }

    // ACCESSORS /////////////////////////////////////////////

    public function count(): int {
        return $this->attempts->count();
    }

    public function isEmpty(): bool {
        return $this->attempts->isEmpty();
    }

    public function list(): ArrayList {
        return $this->attempts;
    }

    public function all(): array {
        return $this->attempts->all();
    }

    public function first(): ?InferenceAttempt {
        return $this->attempts->first();
    }

    public function last(): ?InferenceAttempt {
        return $this->attempts->last();
    }

    public function usage(): Usage {
        return array_reduce(
            $this->attempts->all(),
            fn (Usage $carry, InferenceAttempt $attempt) => $attempt->isFinalized()
                ? $carry->withAccumulated($attempt->usage())
                : $carry,
            Usage::none()
        );
    }

    // MUTATORS //////////////////////////////////////////////

    public function withNewAttempt(InferenceAttempt $attempt): self {
        return new self($this->attempts->withAppended($attempt));
    }

    /**
     * Updates an attempt with the given one (by matching ID).
     *
     * @throws InvalidArgumentException If no attempt with the given ID exists
     */
    public function withUpdatedAttempt(InferenceAttempt $attempt): self {
        $items = $this->attempts->all();
        $updated = false;
        foreach ($items as $index => $existing) {
            if ($existing->id === $attempt->id) {
                $items[$index] = $attempt;
                $updated = true;
                break;
            }
        }
        if (!$updated) {
            throw new InvalidArgumentException(
                "Cannot update attempt: no attempt found with ID '{$attempt->id}'"
            );
        }
        return new self(ArrayList::fromArray($items));
    }

    // SERIALIZATION /////////////////////////////////////////

    public function toArray(): array {
        return array_map(
            fn (InferenceAttempt $attempt) => $attempt->toArray(),
            $this->attempts->all()
        );
    }

    public static function fromArray(array $data): self {
        $attempts = array_map(
            fn (array $item) => InferenceAttempt::fromArray($item),
            $data
        );
        $list = ArrayList::fromArray($attempts);
        return new self($list);
    }
}
