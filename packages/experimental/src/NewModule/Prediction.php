<?php declare(strict_types=1);

namespace Cognesy\Experimental\NewModule;

use Cognesy\Pipeline\Contracts\CanCarryState;
use Cognesy\Pipeline\Contracts\TagInterface;
use Cognesy\Pipeline\Contracts\TagMapInterface;
use Cognesy\Pipeline\Internal\IndexedTagMap;
use Cognesy\Pipeline\Tag\ErrorTag;
use Cognesy\Pipeline\Tag\TagQuery;
use Cognesy\Pipeline\TransformState;
use Cognesy\Utils\Result\Result;
use RuntimeException;
use Throwable;

/**
 * Prediction - DSPy-style immutable prediction result with typed metadata
 * 
 * Implements CanCarryState to leverage pipeline infrastructure while providing
 * domain-specific conveniences for ML/LLM prediction workflows.
 * 
 * Key Features:
 * - Result monad for success/failure handling
 * - Typed tag system for prediction metadata (reasoning, confidence, usage)
 * - Full pipeline compatibility via CanCarryState
 * - Immutable transformations via TransformState
 */
final readonly class Prediction implements CanCarryState
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

    // FACTORY METHODS (CanCarryState implementation)

    public static function empty(): self
    {
        return new self(
            result: Result::success(null),
            tags: self::defaultTagMap(),
        );
    }

    /**
     * @param mixed $value The prediction output value
     * @param array<TagInterface> $tags Optional prediction tags
     */
    public static function with(mixed $value, array $tags = []): self
    {
        return new self(
            result: Result::from($value),
            tags: self::defaultTagMap($tags),
        );
    }

    // PREDICTION-SPECIFIC FACTORY METHODS

    /**
     * Create successful prediction with output
     */
    public static function success(mixed $output): self
    {
        return self::with($output);
    }

    /**
     * Create failed prediction
     */
    public static function failure(string|Throwable $error): self
    {
        return self::empty()->failWith($error);
    }

    // CORE STATE OPERATIONS (CanCarryState implementation)

    public function withResult(Result $result): self
    {
        return new self($result, $this->tags);
    }

    public function addTags(TagInterface ...$tags): self
    {
        return new self($this->result, $this->tags->add(...$tags));
    }

    public function replaceTags(TagInterface ...$tags): self
    {
        return new self($this->result, $this->tags->replace(...$tags));
    }

    public function failWith(string|Throwable $cause): self
    {
        $message = $cause instanceof Throwable ? $cause->getMessage() : $cause;
        $exception = match (true) {
            is_string($cause) => new RuntimeException($cause),
            $cause instanceof Throwable => $cause,
        };
        return $this
            ->withResult(Result::failure($exception))
            ->addTags(new ErrorTag(error: $message));
    }

    // RESULT ACCESS (CanCarryState implementation)

    public function result(): Result
    {
        return $this->result;
    }

    public function value(): mixed
    {
        if ($this->result->isFailure()) {
            throw new RuntimeException('Cannot unwrap value from a failed prediction');
        }
        return $this->result->unwrap();
    }

    public function valueOr(mixed $default): mixed
    {
        return $this->result->valueOr($default);
    }

    public function isSuccess(): bool
    {
        return $this->result->isSuccess();
    }

    public function isFailure(): bool
    {
        return $this->result->isFailure();
    }

    public function exception(): Throwable
    {
        if ($this->result->isSuccess()) {
            throw new RuntimeException('Cannot get exception from a successful prediction');
        }
        return $this->result->exception();
    }

    public function exceptionOr(mixed $default): mixed
    {
        return $this->result->exceptionOr($default);
    }

    // TAG OPERATIONS (CanCarryState implementation)

    public function tagMap(): TagMapInterface
    {
        return $this->tags;
    }

    /**
     * Get all tags, optionally filtered by class.
     *
     * @param class-string|null $tagClass Optional class filter
     * @return TagInterface[]
     */
    public function allTags(?string $tagClass = null): array
    {
        return $this->tags->query()->only($tagClass)->all();
    }

    /**
     * @param class-string $tagClass
     */
    public function hasTag(string $tagClass): bool
    {
        return $this->tags->has($tagClass);
    }

    // ESSENTIAL TRANSFORMATIONS (CanCarryState implementation)

    public function tags(): TagQuery
    {
        return $this->tagMap()->query();
    }

    public function transform(): TransformState
    {
        return new TransformState($this);
    }

    // PREDICTION-SPECIFIC CONVENIENCES

    /**
     * Get reasoning/chain-of-thought from prediction
     */
    public function reasoning(): ?string
    {
        return $this->tags()->first(ReasoningTag::class)?->reasoning;
    }

    /**
     * Get confidence score from prediction
     */
    public function confidence(): ?float
    {
        return $this->tags()->first(ConfidenceTag::class)?->confidence;
    }

    /**
     * Get token usage information
     */
    public function usage(): ?UsageTag
    {
        return $this->tags()->first(UsageTag::class);
    }

    /**
     * Get model ID used for prediction
     */
    public function modelId(): ?string
    {
        return $this->tags()->first(ModelTag::class)?->modelId;
    }

    /**
     * Add reasoning to prediction
     */
    public function withReasoning(string $reasoning): self
    {
        return $this->addTags(new ReasoningTag($reasoning));
    }

    /**
     * Add confidence score to prediction
     */
    public function withConfidence(float $confidence): self
    {
        return $this->addTags(new ConfidenceTag($confidence));
    }

    /**
     * Add usage information to prediction
     */
    public function withUsage(int $inputTokens, int $outputTokens, float $cost = 0.0): self
    {
        return $this->addTags(new UsageTag($inputTokens, $outputTokens, $cost));
    }

    /**
     * Add model information to prediction
     */
    public function withModel(string $modelId): self
    {
        return $this->addTags(new ModelTag($modelId));
    }

    // PREDICTION ANALYSIS HELPERS

    /**
     * Check if prediction has low confidence
     */
    public function hasLowConfidence(float $threshold = 0.5): bool
    {
        $confidence = $this->confidence();
        return $confidence !== null && $confidence < $threshold;
    }

    /**
     * Get total token cost from all usage tags
     */
    public function totalCost(): float
    {
        return array_sum(array_map(
            fn(UsageTag $tag) => $tag->cost,
            $this->allTags(UsageTag::class)
        ));
    }

    /**
     * Get total tokens (input + output) from all usage tags
     */
    public function totalTokens(): int
    {
        return array_sum(array_map(
            fn(UsageTag $tag) => $tag->inputTokens + $tag->outputTokens,
            $this->allTags(UsageTag::class)
        ));
    }

    // PRIVATE HELPERS

    private static function defaultTagMap(?array $tags = []): TagMapInterface
    {
        return match(true) {
            $tags === null => IndexedTagMap::empty(),
            default => IndexedTagMap::create($tags),
        };
    }
}