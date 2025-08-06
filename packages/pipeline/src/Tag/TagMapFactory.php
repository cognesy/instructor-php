<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Tag;

use Cognesy\Pipeline\Contracts\TagMapInterface;
use Cognesy\Pipeline\Tag\Internal\SimpleTagMap;

class TagMapFactory {
    public static function create(array $tags = []): TagMapInterface {
        return SimpleTagMap::create($tags);
    }

    public static function empty(): TagMapInterface {
        return SimpleTagMap::empty();
    }
}