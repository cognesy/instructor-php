<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Enums;

enum BatchMutationCertainty: string
{
    case NotSent = 'not_sent';
    case Rejected = 'rejected';
    case MayHaveSucceeded = 'may_have_succeeded';
}
