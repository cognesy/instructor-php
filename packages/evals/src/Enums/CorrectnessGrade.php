<?php declare(strict_types=1);

namespace Cognesy\Evals\Enums;

enum CorrectnessGrade : string
{
    case Incorrect = 'incorrect';
    case PartiallyCorrect = 'partially_correct';
    case Correct = 'correct';

    public function toFloat() : float
    {
        return match ($this) {
            self::Incorrect => 0.0,
            self::PartiallyCorrect => 0.5,
            self::Correct => 1.0,
        };
    }
}
