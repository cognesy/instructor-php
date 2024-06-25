<?php

namespace Cognesy\Instructor\Evaluation;

use Cognesy\Instructor\Evaluation\Data\PromptEvaluation;
use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Utils\Template;
use Spatie\ArrayToXml\ArrayToXml;

class EvaluationRenderer {
    public function render(PromptEvaluation $evaluation) : string {
        $prompt = $evaluation->prompt;
        $input = $evaluation->input;
        $actual = $evaluation->actualResult;
        $expected = $evaluation->expectedResult;

        $output = [];
        $output[] = $this->renderPrompt($prompt);
        $output[] = $this->renderInput($input);
        $output[] = $this->renderExpected($expected);
        $output[] = $this->renderActual($actual);
        return implode("\n", $output);
    }

    private function renderPrompt(string $prompt) : string {
        $output = [
            'comment' => 'original prompt - subject of your task',
            'prompt' => ['_cdata' => Template::cleanVarMarkers($prompt)],
        ];
        return $this->toString($output);
    }

    private function renderInput(mixed $input) : string {
        if (empty($input)) return '';
        $output = [
            'comment' => 'input data - data provided by the user',
            'input' => ['_cdata' => "```json\n" . Json::encode($input, JSON_UNESCAPED_SLASHES) . "\n```"],
        ];
        return $this->toString($output);
    }

    private function renderActual(mixed $actual) : string {
        if (empty($actual)) return '';
        $output = [
            'comment' => 'actual result - response from the user',
            'actual-result' => ['_cdata' => "```json\n" . Json::encode($actual, JSON_UNESCAPED_SLASHES) . "\n```"],
        ];
        return $this->toString($output);
    }

    private function renderExpected(mixed $expected) : string {
        if (empty($expected)) return '';
        $output = [
            'comment' => 'expected result - correct response',
            'expected-result' => ['_cdata' => "```json\n" . Json::encode($expected, JSON_UNESCAPED_SLASHES) . "\n```"],
        ];
        return $this->toString($output);
    }

    private function toString(array $output) : string {
        return ((new ArrayToXml($output, 'xml'))->dropXmlDeclaration()->toXml());
    }
}
