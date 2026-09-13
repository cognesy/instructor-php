<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

enum TellPublicationStatus: string
{
    case NotApplicable = 'not_applicable';
    case NotAttempted = 'not_attempted';
    case Published = 'published';
    case Failed = 'failed';
}
