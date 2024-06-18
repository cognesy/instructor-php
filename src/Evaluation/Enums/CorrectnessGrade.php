<?php

namespace Cognesy\Instructor\Evaluation\Enums;

enum CorrectnessGrade : string
{
    case Incorrect = 'incorrect';
    case MostlyIncorrect = 'mostly_incorrect';
    case PartiallyIncorrect = 'partially_incorrect';
    case AlmostCorrect = 'almost_correct';
    case Correct = 'correct';

    public function toFloat() : float
    {
        return match ($this) {
            self::Incorrect => 0.0,
            self::MostlyIncorrect => 0.2,
            self::PartiallyIncorrect => 0.5,
            self::AlmostCorrect => 0.8,
            self::Correct => 1.0,
        };
    }
}
