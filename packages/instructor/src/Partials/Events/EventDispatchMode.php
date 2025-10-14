<?php declare(strict_types=1);

namespace Cognesy\Instructor\Partials\Events;

enum EventDispatchMode
{
    case Strict;   // Throw on listener errors
    case Lenient;  // Log and continue on listener errors
    case Silent;   // Suppress all events
}
