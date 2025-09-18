<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Tag;

use Cognesy\Utils\TagMap\Contracts\TagInterface;

readonly class SkipProcessingTag implements TagInterface {
    public function __construct(
        public string $reason = 'No reason provided',
    ) {}
}