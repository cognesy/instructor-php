<?php

namespace Cognesy\Instructor\Extras\Module\Modules\Web\Data;

class PageData
{
    public function __construct(
        string $title = '',
        string $body = '',
        string $markdown = '',
        /** @var string[] $metadata */
        array $metadata = [],
        /** @var Link[] $links */
        array $links = [],
    ) {}
}