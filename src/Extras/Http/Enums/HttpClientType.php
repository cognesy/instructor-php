<?php

namespace Cognesy\Instructor\Extras\Http\Enums;

enum HttpClientType : string
{
    case Guzzle = 'guzzle';
    case Symfony = 'symfony';
    case Unknown = 'unknown';
}
