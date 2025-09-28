<?php declare(strict_types=1);

namespace Cognesy\Experimental\Pipeline2\Deferred\Enums;

enum ExecutionStage : string
{
    case Init = 'init';
    case BeforeNext = 'before-next';
    case AfterNext = 'after-next';
    case TerminalIn = 'terminal-in';
    case Terminal = 'terminal';
}
