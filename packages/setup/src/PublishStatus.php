<?php declare(strict_types=1);

namespace Cognesy\Setup;

enum PublishStatus: string
{
    case Published = 'published';
    case Skipped = 'skipped';
    case Error = 'error';
}
