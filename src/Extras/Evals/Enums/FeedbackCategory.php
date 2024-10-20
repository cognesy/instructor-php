<?php

namespace Cognesy\Instructor\Extras\Evals\Enums;

enum FeedbackCategory : string
{
    case Error = 'Error';
    case Improvement = 'Improvement';
    case Other = 'Other';
}
