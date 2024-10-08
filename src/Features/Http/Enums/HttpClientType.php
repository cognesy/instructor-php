<?php

namespace Cognesy\Instructor\Features\Http\Enums;

enum HttpClientType : string
{
    case Guzzle = 'guzzle';
    case Symfony = 'symfony';
    case Unknown = 'unknown';
}
