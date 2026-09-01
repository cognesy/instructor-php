<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition\Standalone\Host;

enum TellCapabilityCardinality: string
{
    case Singleton = 'singleton';
    case OptionalSingleton = 'optional-singleton';
    case OrderedContribution = 'ordered-contribution';
}
