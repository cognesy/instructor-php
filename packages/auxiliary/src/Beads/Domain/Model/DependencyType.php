<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Domain\Model;

/**
 * Dependency Type Enum
 *
 * Represents the type of relationship between tasks
 */
enum DependencyType: string
{
    case Blocks = 'blocks';
    case Related = 'related';
    case Parent = 'parent';
    case DiscoveredFrom = 'discovered-from';

    /**
     * Check if this is a blocking dependency
     */
    public function isBlocking(): bool
    {
        return $this === self::Blocks;
    }

    /**
     * Check if this is a parent-child relationship
     */
    public function isParent(): bool
    {
        return $this === self::Parent;
    }

    /**
     * Check if this represents discovered work
     */
    public function isDiscoveredFrom(): bool
    {
        return $this === self::DiscoveredFrom;
    }

    /**
     * Check if this is a related (soft) dependency
     */
    public function isRelated(): bool
    {
        return $this === self::Related;
    }
}
