<?php

namespace Cognesy\Utils\Json\Partial;

enum MultiJsonStrategy
{
    case StopOnFirst;
    case StopOnLast;
    case ParseAll;
}