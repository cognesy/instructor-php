<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Contracts;

use Cognesy\Pipeline\TransformState;
use Cognesy\Utils\TagMap\Contracts\CanCarryTags;

interface CanCarryState extends CanCarryTags, CanCarryResult
{
    public static function with(mixed $value, array $tags = []): static;
    public static function empty(): static;
    public function transform() : TransformState;
}