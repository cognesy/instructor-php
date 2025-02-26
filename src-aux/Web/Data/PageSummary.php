<?php

namespace Cognesy\Auxiliary\Web\Data;

class PageSummary
{
    public function __construct(
        public string $summary,
        public array $links,
    ) {}
}