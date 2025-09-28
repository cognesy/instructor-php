<?php declare(strict_types=1);

namespace Cognesy\Experimental\ModPredict\Optimize\Enums;

enum PromptSelectionStrategy : string
{
    case Active = 'active';
    case Canary = 'canary';
    case None = 'none';
}