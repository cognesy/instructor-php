<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

enum TellExtensionKind: string
{
    case Agent = 'agent';
    case Provider = 'provider';
    case Model = 'model';
    case Capability = 'capability';
    case Tool = 'tool';
}
