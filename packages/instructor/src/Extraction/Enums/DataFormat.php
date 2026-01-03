<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Enums;

/** @internal */
enum DataFormat: string
{
    case Json = 'json';
    case Yaml = 'yaml';
    case Xml = 'xml';
}
