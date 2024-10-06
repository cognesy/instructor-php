<?php

namespace Cognesy\Instructor\Extras\Enums;

enum HttpClientType : string
{
    case Guzzle = 'guzzle';
    case Symfony = 'symfony';
    case Unknown = 'unknown';
}
