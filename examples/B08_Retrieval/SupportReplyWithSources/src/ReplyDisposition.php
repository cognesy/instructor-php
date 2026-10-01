<?php

declare(strict_types=1);

namespace Examples\Retrieval\SupportReplyWithSources;

enum ReplyDisposition: string
{
    case ReadyForReview = 'ready_for_review';
    case InsufficientEvidence = 'insufficient_evidence';
}
