<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Protocol;

use Cognesy\Dynamic\Field;
use Cognesy\Dynamic\Structure;
use Cognesy\Instructor\Validation\ValidationResult;

final class RlmActionStructures
{
    /**
     * Returns a Structure enforcing one of: plan | tool | write | final | await.
     */
    public static function decision(): Structure {
        $type = Field::option('type', ['plan', 'tool', 'write', 'final', 'await'], 'RLM action type.')->required();
        return Structure::define('rlm_action', [
            $type,
            Field::array('subtasks', 'Subtasks for plan (optional).'),
            Field::string('name', 'Tool name (for type=tool).'),
            Field::array('args', 'Tool args (for type=tool).'),
            Field::string('var', 'Variable name (for type=write).'),
            Field::string('from', 'Handle string (for type=write/final).'),
            Field::string('reason', 'Reason (for type=await).'),
            Field::array('expected', 'Expected inputs (for type=await).'),
        ], 'Recursive Language Model action.')->withValidation(fn(Structure $s) => self::validate($s));
    }

    private static function validate(Structure $s): ValidationResult {
        $type = (string)($s->get('type') ?? '');
        return match ($type) {
            'plan' => ValidationResult::valid(),
            'tool' => self::validateTool($s),
            'write' => self::validateWrite($s),
            'final' => self::validateFinal($s),
            'await' => self::validateAwait($s),
            default => ValidationResult::fieldError('type', $type, 'Invalid action type.')
        };
    }

    private static function validateTool(Structure $s): ValidationResult {
        $name = (string)($s->get('name') ?? '');
        $args = $s->get('args');
        if ($name === '') {
            return ValidationResult::fieldError('name', $name, 'Tool name is required for type=tool.');
        }
        if (!is_array($args)) {
            return ValidationResult::fieldError('args', $args, 'Args must be an object for type=tool.');
        }
        return ValidationResult::valid();
    }

    private static function validateWrite(Structure $s): ValidationResult {
        $var = (string)($s->get('var') ?? '');
        $from = (string)($s->get('from') ?? '');
        if ($var === '') {
            return ValidationResult::fieldError('var', $var, 'Var is required for type=write.');
        }
        if ($from === '') {
            return ValidationResult::fieldError('from', $from, 'From handle is required for type=write.');
        }
        return ValidationResult::valid();
    }

    private static function validateFinal(Structure $s): ValidationResult {
        $from = (string)($s->get('from') ?? '');
        if ($from === '') {
            return ValidationResult::fieldError('from', $from, 'Return handle is required for type=final.');
        }
        return ValidationResult::valid();
    }

    private static function validateAwait(Structure $s): ValidationResult {
        $reason = (string)($s->get('reason') ?? '');
        if ($reason === '') {
            return ValidationResult::fieldError('reason', $reason, 'Reason is required for type=await.');
        }
        return ValidationResult::valid();
    }
}

