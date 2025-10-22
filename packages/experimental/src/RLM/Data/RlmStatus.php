<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Data;

enum RlmStatus: string
{
    case Final = 'final';
    case Await = 'await';
    case Failed = 'failed';
}

