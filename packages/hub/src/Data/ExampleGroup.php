<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Data;

use Cognesy\Auxiliary\Mintlify\NavigationGroup;

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

    /**
     * Sort examples by front-matter 'order' property.
     * Examples with order come first (sorted by order value),
     * then examples without order retain their original position.
     */
    public function sortExamples(): void {
        usort($this->examples, function (Example $a, Example $b): int {
            $aOrder = $a->order ?? PHP_INT_MAX;
            $bOrder = $b->order ?? PHP_INT_MAX;
            if ($aOrder !== $bOrder) {
                return $aOrder <=> $bOrder;
            }
            return $a->index <=> $b->index;
        });
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
