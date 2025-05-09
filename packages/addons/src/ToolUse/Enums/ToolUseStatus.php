<?php

namespace Cognesy\Addons\ToolUse\Enums;

enum ToolUseStatus : string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
}