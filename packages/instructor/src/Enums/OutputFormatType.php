<?php declare(strict_types=1);

namespace Cognesy\Instructor\Enums;

enum OutputFormatType: string
{
    case AsArray = 'array';
    case AsClass = 'class';
    case AsObject = 'object';
}
