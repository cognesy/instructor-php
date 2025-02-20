<?php

namespace Cognesy\LLM\Http\Enums;

enum HttpClientType : string
{
    case Guzzle = 'guzzle';
    case Symfony = 'symfony';
    case Laravel = 'laravel';
    case Unknown = 'unknown';
}
