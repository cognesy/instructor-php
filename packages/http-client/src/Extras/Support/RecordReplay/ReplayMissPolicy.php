<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay;

enum ReplayMissPolicy: string
{
    case Fail = 'fail';
    case Passthrough = 'passthrough';
}
