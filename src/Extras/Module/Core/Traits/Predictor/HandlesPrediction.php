<?php
namespace Cognesy\Instructor\Extras\Module\Core\Traits\Predictor;

use Cognesy\Instructor\Extras\Structure\StructureFactory;
use Cognesy\Instructor\Utils\TemplateUtil;
use InvalidArgumentException;

trait HandlesPrediction
{
    public function __invoke(mixed ...$callArgs) : mixed {
        return $this->predict(...$callArgs);
    }

    public function predict(mixed ...$callArgs) : mixed {
        if (!empty($this->signatureDiff($callArgs))) {
            throw new InvalidArgumentException('Missing required arguments: ' . implode(', ', $this->signatureDiff($callArgs)));
        }
        $result = match(true) {
            $this->hasTextOutput() => $this->predictText($callArgs),
            default => $this->predictStructure($callArgs),
        };
        $this->feedbackFn = fn() => $this->provideFeedback(
            input: $callArgs,
            output: $result,
            signature: $this->signature,
            instructions: $this->instructions(),
            roleDescription: $this->roleDescription,
        );
        return $result;
    }

    // INTERNAL ////////////////////////////////////////////////////////////

    protected function predictText(array $callArgs) : mixed {
        $this->requestInfo->prompt = match(true) {
            empty($this->requestInfo->prompt) => TemplateUtil::render(
                template: $this->toTextTemplate(),
                parameters: array_merge([
                    'instructions' => $this->instructions(),
                    'input' => $callArgs,
                ])
            ),
            default => $this->requestInfo->prompt,
        };
        return $this->instructor->withRequest($this->requestInfo)->raw();
    }

    protected function predictStructure(array $callArgs) : mixed {
        $this->requestInfo->input = $callArgs;
        $this->requestInfo->prompt = $this->instructions();
        $this->requestInfo->responseModel = StructureFactory::fromSignature('prediction', $this->signature);
        return $this->instructor->withRequest($this->requestInfo)->get();
    }

    protected function toTemplate(bool $textMode = false) : string {
        return match($textMode) {
            true => $this->toTextTemplate(),
            default => $this->toStructuredTemplate()
        };
    }

    protected function toTextTemplate() : string {
        return <<<TEMPLATE
            YOUR TASK:
            <|instructions|>
            
            INPUT DATA:
            <|input|>
            
            RESPONSE:
        TEMPLATE;
    }

    protected function toStructuredTemplate() : string {
        return <<<TEMPLATE
            YOUR TASK:
            <|instructions|>
            
            INPUT DATA:
            ```json
            <|input|>
            ```
            
            OUTPUT JSON SCHEMA:
            ```json
            <|json_schema|>
            ```
            
            RESPONSE IN JSON:
            ```json
        TEMPLATE;
    }
}