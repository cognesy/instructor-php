<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Internal;

enum DoctestTokenType: string
{
    case Comment = 'comment';
    case DoctestId = 'doctest_id';
    case DoctestRegionStart = 'doctest_region_start';
    case DoctestRegionEnd = 'doctest_region_end';
    case Code = 'code';
    case Newline = 'newline';
    case EOF = 'eof';
}