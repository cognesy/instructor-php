<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

enum TellExecutionMode: string
{
    case Automatic = 'automatic';
    case Stateless = 'stateless';
    case Durable = 'durable';
    case Transient = 'transient';
}
