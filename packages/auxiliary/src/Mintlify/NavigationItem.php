<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Mintlify;

class NavigationItem
{
    private bool $isGroup = false;
    private string $page;
    private NavigationGroup $group;

    public static function fromAny(string|array|NavigationItem $data) : NavigationItem {
        return match(true) {
            $data instanceof NavigationItem => $data,
            is_string($data) => self::fromString($data),
            is_array($data) => self::fromArray($data),
            default => throw new \InvalidArgumentException('Invalid argument type - expected string or array')
        };
    }

    public static function fromString(string $data) : NavigationItem {
        $item = new NavigationItem();
        $item->page = $data;
        $item->isGroup = false;
        return $item;
    }

    public static function fromArray(array $data) : NavigationItem {
        $item = new NavigationItem();
        $item->group = NavigationGroup::fromArray($data['group'] ?? []);
        $item->isGroup = true;
        return $item;
    }

    public function getItem() : string|NavigationGroup {
        return $this->isGroup ? $this->group : $this->page;
    }

    public function toArrayItem() : string|array {
        return $this->isGroup ? $this->group->toArray() : $this->page;
    }
}
