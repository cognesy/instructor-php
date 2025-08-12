<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Contracts;

use Cognesy\Pipeline\Tag\TagQuery;
use Cognesy\Pipeline\TransformState;
use Cognesy\Utils\Result\Result;
use Throwable;

interface CanCarryState
{
    // Factory methods
    public static function empty(): self;
    public static function with(mixed $value, array $tags = []): self;

    // Core state operations
    public function withResult(Result $result): self;
    public function addTags(TagInterface ...$tags): self;
    public function replaceTags(TagInterface ...$tags): self;
    public function failWith(string|Throwable $cause): self;

    // Result access
    public function result(): Result;
    public function value(): mixed;
    public function valueOr(mixed $default): mixed;
    public function isSuccess(): bool;
    public function isFailure(): bool;
    public function exception(): Throwable;
    public function exceptionOr(mixed $default): mixed;

    // Tag operations
    public function tagMap(): TagMapInterface;
    public function allTags(?string $tagClass = null): array;
    public function hasTag(string $tagClass): bool;

    // Essential transformations
    public function tags(): TagQuery;
    public function transform() : TransformState;
}