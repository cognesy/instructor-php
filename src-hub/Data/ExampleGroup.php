<?php

namespace Cognesy\InstructorHub\Data;

use Cognesy\InstructorHub\Utils\Mintlify\NavigationGroup;

class ExampleGroup
{
    public function __construct(
        public string $name = '',
        public string $title = '',
        /** @var Example[] */
        public array $examples = [],
    ) {}

    public function addExample(Example $example): void {
        $this->examples[] = $example;
    }

    public function toNavigationGroup(): NavigationGroup {
        return new NavigationGroup(
            group: $this->title,
            pages: array_map(function($example) {
                return $example->toNavigationItem();
            }, $this->examples),
        );
    }
}
