<?php declare(strict_types=1);

namespace Cognesy\Instructor\Enums;

enum AttemptPhase: string
{
    case Init = 'init';
    case Streaming = 'streaming';
    case Validating = 'validating';
    case Done = 'done';
}

