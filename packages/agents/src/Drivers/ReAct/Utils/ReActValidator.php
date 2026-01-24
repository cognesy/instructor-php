<?php declare(strict_types=1);

namespace Cognesy\Agents\Drivers\ReAct\Utils;

use Cognesy\Agents\Drivers\ReAct\Contracts\Decision;
use Cognesy\Dynamic\Structure;
use Cognesy\Instructor\Validation\ValidationResult;

final class ReActValidator
{
    /** Basic validation: decision type and selected tool presence. */
    public function validateBasicDecision(Decision $decision, array $toolNames): ValidationResult {
        $type = $decision->type();
        if ($type === 'call_tool') {
            $tool = $decision->tool() ?? '';
            if ($tool === '') {
                return ValidationResult::fieldError('tool', $tool, 'Tool name is required when type=call_tool.');
            }
            if ($toolNames !== [] && !in_array($tool, $toolNames, true)) {
                return ValidationResult::fieldError('tool', $tool, 'Requested tool is not available.');
            }
            return ValidationResult::valid();
        }
        if ($type === 'final_answer') {
            return $this->validateFinalDecision($decision);
        }
        return ValidationResult::fieldError('type', $type, 'Decision type must be call_tool or final_answer.');
    }

    /** Ensures args presence when the selected tool requires them. */
    public function validateArgsForCall(Decision $decision, ?Structure $argStructure): ValidationResult {
        $args = $decision->args();
        $hasArgs = $args !== [];

        // Check if args is an associative array (object) not a sequential array (list)
        if ($hasArgs && !$this->isAssociativeArray($args)) {
            return ValidationResult::fieldError('args', $args, 'Arguments must be a JSON object with parameter names as keys, not a sequential array.');
        }

        $requiresArgs = $this->toolRequiresArgs($argStructure);
        if ($requiresArgs && !$hasArgs) {
            return ValidationResult::fieldError('args', $args, 'Arguments are required when type=call_tool.');
        }
        return ValidationResult::valid();
    }

    /** Ensures final_answer decisions contain an answer. */
    public function validateFinalDecision(Decision $decision): ValidationResult {
        $answer = $decision->answer();
        if ($answer === '') {
            return ValidationResult::fieldError('answer', $answer, 'Answer is required when type=final_answer.');
        }
        return ValidationResult::valid();
    }

    public function toolRequiresArgs(?Structure $argsStructure): bool {
        if ($argsStructure === null) {
            return true;
        }
        foreach ($argsStructure->fields() as $field) {
            if ($field->isRequired()) {
                return true;
            }
        }
        return false;
    }

    /** Check if array is associative (has string keys) rather than sequential (numeric keys). */
    private function isAssociativeArray(array $array): bool {
        if ($array === []) {
            return true; // Empty array is considered associative
        }
        // Check if all keys are strings
        return array_keys($array) !== range(0, count($array) - 1);
    }
}

