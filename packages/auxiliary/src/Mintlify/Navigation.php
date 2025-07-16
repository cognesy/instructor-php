<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Mintlify;

class Navigation
{
    public array $groups = [];

    public static function fromArray(array $data) : Navigation {
        $navigation = new Navigation();
        foreach($data as $group) {
            $navigation->groups[] = NavigationGroup::fromArray($group);
        }
        return $navigation;
    }

    public function toArray() : array {
        $array = [];
        foreach($this->groups as $group) {
            $array[$group->group] = $group->toArray();
        }
        return $array;
    }

    public function removeGroups(array $names) : void {
        foreach($names as $name) {
            $this->removeGroup($name);
        }
    }

    public function removeGroup(string $name) : void {
        $this->groups = array_filter($this->groups, function($group) use ($name) {
            return $group->group !== $name;
        });
    }

    public function appendGroup(NavigationGroup $group) : void {
        $this->groups[] = $group;
    }

    public function appendGroups(array $groups) : void {
        foreach($groups as $group) {
            if (!($group instanceof NavigationGroup)) {
                throw new \InvalidArgumentException('Invalid argument type - expected NavigationGroup');
            }
            $this->appendGroup($group);
        }
    }
}
