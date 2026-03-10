<?php declare(strict_types=1);

namespace Cognesy\Setup\Config;

final readonly class ConfigPublishPlan
{
    /** @var list<ConfigPublishEntry> */
    private array $entries;

    /**
     * @param list<ConfigPublishEntry> $entries
     */
    public function __construct(array $entries)
    {
        $this->entries = $entries;
    }

    /** @return list<ConfigPublishEntry> */
    public function entries(): array
    {
        return $this->entries;
    }

    /** @return list<string> */
    public function packages(): array
    {
        $packages = array_map(
            fn(ConfigPublishEntry $entry): string => $entry->package,
            $this->entries,
        );

        return array_values(array_unique($packages));
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }
}
