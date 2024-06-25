<?php
namespace Cognesy\Instructor\Evaluation;

use Cognesy\Instructor\Data\Example;
use Cognesy\Instructor\Evaluation\Data\Feedback;
use Cognesy\Instructor\Evaluation\Data\PromptEvaluation;
use Cognesy\Instructor\Utils\Template;
use Spatie\ArrayToXml\ArrayToXml;

class FeedbackRenderer {
    public function render(PromptEvaluation $evaluation) : string {
        $prompt = $evaluation->prompt;
        $examples = $evaluation->examples;
        $feedback = $evaluation->result->feedback();

        $output = [];
        $output[] = $this->renderPrompt($prompt);
        $output[] = $this->renderFeedback($feedback);
        $output[] = $this->renderExamples($examples);
        return implode("\n", $output);
    }

    private function renderPrompt(string $prompt) : string {
        $output = [
            'comment' => 'original prompt - subject of your task',
            'prompt' => ['_cdata' => Template::cleanVarMarkers($prompt)],
        ];
        return $this->toString($output);
    }

    private function renderFeedback(Feedback $feedback) : string {
        $output = ['comment' => 'feedback on the actual result'];
        foreach ($feedback->items() as $value) {
            $output['feedback'][] = [
                'variable' => $value->parameterName,
                'feedback-content' => ['_cdata' => $value->feedback],
            ];
            $output['feedback'][] = [
                'variable' => $value->parameterName,
                'feedback-content' => ['_cdata' => $value->feedback],
            ];
        }
        return $this->toString($output);
    }

    /**
     * @param Example[] $examples
     */
    private function renderExamples(array $examples) : string {
        $output = ['comment' => 'examples of inputs and expected outputs'];
        foreach ($examples as $example) {
            $output['examples'][] = $example->toXmlArray();
        }
        return $this->toString($output);
    }

    private function toString(array $output) : string {
        return ((new ArrayToXml($output, 'xml'))->dropXmlDeclaration()->toXml());
    }
}
