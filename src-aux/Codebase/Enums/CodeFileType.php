<?php

namespace Cognesy\Auxiliary\Codebase\Enums;

enum CodeFileType : string
{
    case Code = 'code';
    case Example = 'example';
    case Test = 'test';
    case Config = 'config';
    case Docs = 'docs';
    case Other = 'other';
}
