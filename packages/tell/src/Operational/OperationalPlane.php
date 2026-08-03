<?php

declare(strict_types=1);

namespace Cognesy\Tell\Operational;

enum OperationalPlane: string
{
    case Data = 'data';
    case Control = 'control';
    case Management = 'management';
}
