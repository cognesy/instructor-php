<?php

namespace Cognesy\Evals\Enums;

enum FeedbackType : string
{
    case Error = 'Error';
    case Improvement = 'Improvement';
    case Other = 'Other';
}
