<?php declare(strict_types=1);

namespace Cognesy\Utils\Json\Partial;

enum TokenType: string
{
    case LeftBrace    = '{';
    case RightBrace   = '}';
    case LeftBracket  = '[';
    case RightBracket = ']';
    case Colon        = ':';
    case Comma        = ',';

    case String        = 'STRING';
    case StringPartial = 'STRING_PARTIAL';
    case Number        = 'NUMBER';
    case NumberPartial = 'NUMBER_PARTIAL';

    case True  = 'TRUE';
    case False = 'FALSE';
    case Null  = 'NULL';
}
