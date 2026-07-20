<?php declare(strict_types=1);

namespace Cognesy\Instructor\Enums;

enum ResponseFailureStage: string
{
    case Extraction = 'extraction';
    case SchemaValidation = 'schema_validation';
    case Deserialization = 'deserialization';
    case ObjectValidation = 'object_validation';
    case Transformation = 'transformation';
}
