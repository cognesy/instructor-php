<?php

namespace Cognesy\Instructor\Extras\Module\Call\Enums;

enum CallStatus : string
{
    case Created = 'created';
    case Ready = 'ready';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
}
