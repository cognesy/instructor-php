<?php declare(strict_types=1);

namespace Cognesy\Doctor\Markdown\Enums;

enum MetadataStyle: string
{
    case Fence = 'fence';
    case Comments = 'comments';
}