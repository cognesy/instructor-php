<?php declare(strict_types=1);

namespace Cognesy\Instructor\Creation\ResponseModel;

use Cognesy\Instructor\Data\OutputFormat;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\ResponseModel\ResponseModelBuildModeSelected;
use Cognesy\Schema\Data\Schema;
use Cognesy\Schema\TypeInfo;

/**
 * Resolves a Schema object: its type metadata selects the default class target,
 * while a class-less schema defaults to an array.
 */
final readonly class SchemaObjectResolver
{
    public function __construct(private ResolutionSupport $support) {}

    public function resolve(Schema $schema, ?OutputFormat $outputFormat): ResponseModel {
        $this->support->events()->dispatch(new ResponseModelBuildModeSelected(['mode' => 'fromSchema']));

        $rendering = $this->support->renderSchema($schema);

        return $this->support->assemble(
            instance: null,
            schema: $schema,
            jsonSchema: $rendering->jsonSchema(),
            schemaName: $this->support->schemaName($schema),
            schemaDescription: $this->support->schemaDescription($schema),
            outputFormat: $outputFormat,
            rendering: $rendering,
        );
    }
}
