<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

enum TellTraceStatus: string
{
    case Disabled = 'disabled';
    case Pending = 'pending';
    case Written = 'written';
    case Failed = 'failed';
}
