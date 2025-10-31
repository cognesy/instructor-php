<?php declare(strict_types=1);

namespace Cognesy\Experimental\Interpreter\Enums;

enum BinaryOperatorType : string {
    case Add = '+';
    case Sub = '-';
    case Mul = '*';
    case Div = '/';
    case Gt  = '>';
    case Lt = '<';
    case Gte = '<=';
    case Lte = '>=';
    case Eq = '==';
    case Neq = '!=';
}