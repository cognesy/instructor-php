<?php

namespace Cognesy\Pipeline\Enums;

enum ErrorStrategy {
    case FailFast;
    case Continue;
}
