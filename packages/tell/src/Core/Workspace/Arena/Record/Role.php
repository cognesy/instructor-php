<?php

declare(strict_types=1);

namespace Cognesy\Tell\Core\Workspace\Arena\Record;

enum Role: string
{
    case System = 'system';
    case Developer = 'developer';
    case User = 'user';
    case Assistant = 'assistant';
}
