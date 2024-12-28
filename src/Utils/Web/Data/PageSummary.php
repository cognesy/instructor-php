<?php

namespace Cognesy\Instructor\Utils\Web\Data;

class PageSummary
{
    public function __construct(
        public string $summary,
        public array $links,
    ) {}
}