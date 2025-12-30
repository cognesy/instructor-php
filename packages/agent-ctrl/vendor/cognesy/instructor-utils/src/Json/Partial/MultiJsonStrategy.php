<?php declare(strict_types=1);

namespace Cognesy\Utils\Json\Partial;

enum MultiJsonStrategy
{
    case StopOnFirst;
    case StopOnLast;
    case ParseAll;
}