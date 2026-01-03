<?php declare(strict_types=1);

namespace Cognesy\Instructor\Data;

enum OutputFormatType: string
{
    case AsArray = 'array';
    case AsClass = 'class';
    case AsObject = 'object';
}
