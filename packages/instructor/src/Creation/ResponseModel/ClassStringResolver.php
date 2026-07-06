<?php declare(strict_types=1);

namespace Cognesy\Instructor\Creation\ResponseModel;

use Cognesy\Instructor\Data\OutputFormat;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\ResponseModel\ResponseModelBuildModeSelected;

/** Resolves a plain class-string: schema derived via reflection. */
final readonly class ClassStringResolver
{
    public function __construct(private ResolutionSupport $support) {}

    public function resolve(string $class, ?OutputFormat $outputFormat): ResponseModel {
        $this->support->events()->dispatch(new ResponseModelBuildModeSelected(['mode' => 'fromClassString']));

        $instance = $this->support->makeInstance($class);
        $schema = $this->support->schemaRenderer()->schemaFactory()->schema($class);
        $rendering = $this->support->renderSchema($schema);

        return $this->support->assemble(
            class: $class,
            instance: $instance,
            schema: $schema,
            jsonSchema: $rendering->jsonSchema(),
            schemaName: $this->support->schemaName($class),
            schemaDescription: $this->support->schemaDescription($class),
            outputFormat: $outputFormat,
            rendering: $rendering,
        );
    }
}
