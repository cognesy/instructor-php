<?php
namespace Cognesy\Instructor\Extras\Web\Data;

class PageSummary {
    public function __construct(
        public string $summary,
        /** @var Link[] */
        public array $links,
    ) {}
}
