<?php
namespace Cognesy\Experimental\Module\Core\Traits\Predictor;

use Cognesy\Experimental\Module\Signature\Signature;
use Cognesy\Instructor\Extras\Structure\StructureFactory;
use Cognesy\Utils\Template\Template;
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

    protected function predictText(array $callArgs) : string {
        $this->requestInfo->prompt = match(true) {
            empty($this->requestInfo->prompt) => Template::arrowpipe()
                ->from($this->toTextTemplate())
                ->with(array_merge([
                    'instructions' => $this->instructions(),
                    'input' => $callArgs,
                ]))
                ->toText(),
            default => $this->requestInfo->prompt,
        };
        return $this->inference->create(messages: $this->requestInfo->prompt)->toText();
    }

    protected function predictStructure(array $callArgs) : mixed {
        $this->requestInfo->input = $callArgs;
        $this->requestInfo->prompt = $this->instructions();
        $this->requestInfo->responseModel = Signature::toStructure('prediction', $this->signature);
        // TODO: replace with new Instructor API call
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