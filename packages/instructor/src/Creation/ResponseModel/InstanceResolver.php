<?php declare(strict_types=1);

namespace Cognesy\Instructor\Creation\ResponseModel;

use Cognesy\Instructor\Data\OutputFormat;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\ResponseModel\ResponseModelBuildModeSelected;

/** Resolves a plain object instance: schema derived from its class via reflection. */
final readonly class InstanceResolver
{
    public function __construct(private ResolutionSupport $support) {}

    public function resolve(object $instance, ?OutputFormat $outputFormat): ResponseModel {
        $this->support->events()->dispatch(new ResponseModelBuildModeSelected(['mode' => 'fromInstance']));

        $class = get_class($instance);
        $schema = $this->support->schemaRenderer()->schemaFactory()->schema($class);
        $rendering = $this->support->renderSchema($schema);

        return $this->support->assemble(
            class: $class,
            instance: $instance,
            schema: $schema,
            jsonSchema: $rendering->jsonSchema(),
            schemaName: $this->support->schemaName($instance),
            schemaDescription: $this->support->schemaDescription($instance),
            outputFormat: $outputFormat,
            rendering: $rendering,
        );
    }
}
