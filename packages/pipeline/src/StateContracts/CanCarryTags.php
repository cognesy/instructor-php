<?php declare(strict_types=1);

namespace Cognesy\Pipeline\StateContracts;

use Cognesy\Utils\TagMap\Contracts\TagInterface;
use Cognesy\Utils\TagMap\Contracts\TagMapInterface;
use Cognesy\Utils\TagMap\TagQuery;

interface CanCarryTags
{
    public function addTags(TagInterface ...$tags): static;
    public function replaceTags(TagInterface ...$tags): static;
    public function tagMap(): TagMapInterface;
    public function allTags(?string $tagClass = null): array;
    public function hasTag(string $tagClass): bool;
    public function tags(): TagQuery;
}