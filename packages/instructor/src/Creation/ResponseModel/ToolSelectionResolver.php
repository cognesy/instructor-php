<?php declare(strict_types=1);

namespace Cognesy\Instructor\Creation\ResponseModel;

use Cognesy\Instructor\Contracts\CanHandleToolSelection;
use Cognesy\Instructor\Data\OutputFormat;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Events\ResponseModel\ResponseModelBuildModeSelected;

/**
 * Resolves a CanHandleToolSelection provider (class-string or instance):
 * the provider supplies both JSON schema and Schema, plus its own tool
 * definitions (consumed inside ResolutionSupport::assemble()).
 */
final readonly class ToolSelectionResolver
{
    public function __construct(private ResolutionSupport $support) {}

    public function resolve(CanHandleToolSelection|string $requestedModel, ?OutputFormat $outputFormat): ResponseModel {
        if (is_string($requestedModel)) {
            [, $instance] = $this->support->resolveClassAndInstance($requestedModel);
            assert($instance instanceof CanHandleToolSelection);
            $requestedModel = $instance;
        }

        $this->support->events()->dispatch(new ResponseModelBuildModeSelected(['mode' => 'fromToolSelectionProvider']));

        $schema = $requestedModel->toSchema();

        return $this->support->assemble(
            instance: $requestedModel,
            schema: $schema,
            jsonSchema: $requestedModel->toJsonSchema(),
            schemaName: $this->support->schemaName($schema),
            schemaDescription: $this->support->schemaDescription($requestedModel),
            outputFormat: $outputFormat,
        );
    }
}
