<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

enum TellCapabilityCardinality: string
{
    case Singleton = 'singleton';
    case OptionalSingleton = 'optional-singleton';
    case OrderedContribution = 'ordered-contribution';
}
