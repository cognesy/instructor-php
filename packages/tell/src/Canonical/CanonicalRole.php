<?php

declare(strict_types=1);

namespace Cognesy\Tell\Canonical;

enum CanonicalRole: string
{
    case System = 'system';
    case Developer = 'developer';
    case User = 'user';
    case Assistant = 'assistant';
}
