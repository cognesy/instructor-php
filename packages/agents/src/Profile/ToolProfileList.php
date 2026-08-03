<?php declare(strict_types=1);

namespace Cognesy\Agents\Profile;

use Cognesy\Agents\Collections\NameList;
use Cognesy\Agents\Collections\Tools;

final readonly class ToolProfileList
{
    /** @var array<string, ToolProfile> */
    private array $profiles;

    public function __construct(ToolProfile ...$profiles) {
        $indexed = [];
        foreach ($profiles as $profile) {
            $indexed[$profile->name] = $profile;
        }
        $this->profiles = $indexed;
    }

    public static function fromTools(Tools $tools, ?NameList $deferredNames = null): self {
        $deferredNames ??= new NameList();
        $profiles = [];
        foreach ($tools->all() as $tool) {
            $descriptor = $tool->descriptor();
            $profiles[] = ToolProfile::fromDescriptor(
                $descriptor,
                deferred: $deferredNames->has($descriptor->name()),
            );
        }
        return new self(...$profiles);
    }

    /** @return list<ToolProfile> */
    public function all(): array {
        return array_values($this->profiles);
    }

    /** @return list<ToolProfile> */
    public function visible(): array {
        return array_values(array_filter(
            $this->profiles,
            static fn (ToolProfile $profile): bool => $profile->promptSnippet !== null,
        ));
    }

    /** @return list<string> */
    public function names(): array {
        return array_keys($this->profiles);
    }

    public function deferredNames(): NameList {
        return new NameList(...array_map(
            static fn (ToolProfile $profile): string => $profile->name,
            array_values(array_filter(
                $this->profiles,
                static fn (ToolProfile $profile): bool => $profile->deferred,
            )),
        ));
    }

    public function has(string $name): bool {
        return isset($this->profiles[$name]);
    }

    public function get(string $name): ToolProfile {
        return $this->profiles[$name];
    }

    public function isEmpty(): bool {
        return $this->profiles === [];
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array {
        return array_map(
            static fn (ToolProfile $profile): array => $profile->toArray(),
            $this->all(),
        );
    }
}
