<?php declare(strict_types=1);

namespace Cognesy\Instructor\Partials\Data;

enum ToolCallEvent
{
    case Started;
    case Updated;
    case Completed;
    case Finalized;
}
