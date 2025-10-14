<?php declare(strict_types=1);

namespace Cognesy\Instructor\Partials\Data;

enum SequenceEventType
{
    case ItemAdded;
    case ItemUpdated;
    case ItemRemoved;
    case SequenceCompleted;
}
