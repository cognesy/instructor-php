<?php

namespace Cognesy\Auxiliary\Mintlify;

class NavigationGroup
{
    public string $group;
    /** @var NavigationItem[] $pages */
    public array $pages;

    public function __construct(string $group = '', array $pages = []) {
        $this->group = $group;
        $this->pages = $this->toNavigationItems($pages);
    }

    public static function fromArray(array $data) : NavigationGroup {
        $group = new NavigationGroup();
        $group->group = $data['group'] ?? '';
        $group->pages = $group->toNavigationItems($data['pages'] ?? []);
        return $group;
    }

    public function toNavigationItems(array $pages) : array {
        return array_map(function($item) {
            return NavigationItem::fromAny($item);
        }, $pages);
    }

    public function toArray() : array {
        return [
            'group' => $this->group,
            'pages' => array_map(function($page) {
                return $page->toArrayItem();
            }, $this->pages)
        ];
    }
}
