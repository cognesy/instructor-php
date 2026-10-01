<?php

declare(strict_types=1);

namespace Examples\Retrieval\SupportReplyWithSources;

use Cognesy\Retrieval\Indexing\Data\TextDocument;
use Generator;

final readonly class KnowledgeBase
{
    public const string APPROVED = 'workspace-standard:approved:2026-10';
    public const string QUESTION = 'My annual subscription renewed five days ago. I no longer need it. Can I get a refund, and how do I stop the next renewal?';
    public const string ACCOUNT = 'Product: Workspace. Plan: Standard annual. Renewal: 2026-09-26. Today: 2026-10-01. Renewal age: 5 calendar days. Usage and refund approval have not been checked.';

    /** @return Generator<int, TextDocument> */
    public function documents(): Generator {
        yield new TextDocument(
            'archived-refund',
            'ARCHIVED REFUND POLICY: Standard annual subscription renewals receive an automatic refund within 30 days. This policy was superseded on 2026-10-01. Do not use it for current support replies.',
            ['title' => 'Archived annual refunds', 'knowledge_set' => 'workspace-standard:archived:2026-09'],
            sourceVersion: '2026-09',
        );
        yield new TextDocument(
            'enterprise-refund',
            'ENTERPRISE REFUND EXCEPTION: Workspace Enterprise annual subscription renewals may be refunded within 90 days under the negotiated contract. This exception does not apply to Standard accounts.',
            ['title' => 'Enterprise contract exception', 'knowledge_set' => 'workspace-enterprise:approved:2026-10'],
            sourceVersion: '2026-10',
        );
        yield new TextDocument(
            'standard-refund',
            'STANDARD REFUND REVIEW: For Workspace Standard annual renewals, customers may submit a refund request within 7 calendar days of renewal. Approval requires a billing specialist to review usage and billing records. Support must not promise approval or claim that a refund has been issued.',
            ['title' => 'Standard annual refund review', 'knowledge_set' => self::APPROVED],
            sourceVersion: '2026-10',
        );
        yield new TextDocument(
            'cancel-renewal',
            'CANCEL NEXT RENEWAL: For Workspace Standard, open Settings > Billing > Subscription and choose Cancel renewal. This stops the next annual renewal. Access continues until the end of the paid period. Cancellation does not automatically refund the latest renewal.',
            ['title' => 'Cancel the next renewal', 'knowledge_set' => self::APPROVED],
            sourceVersion: '2026-10',
        );
        yield new TextDocument(
            'payment-errors',
            'PAYMENT TROUBLESHOOTING: For a declined Workspace Standard card payment, check the billing address and ask the bank about recurring-payment restrictions. Never request full card details in a support message.',
            ['title' => 'Troubleshoot declined payments', 'knowledge_set' => self::APPROVED],
            sourceVersion: '2026-10',
        );
        yield new TextDocument(
            'account-security',
            'ACCOUNT SECURITY: Workspace Standard customers can reset their password from the sign-in screen. Support must never ask for a password or a one-time authentication code.',
            ['title' => 'Account access and security', 'knowledge_set' => self::APPROVED],
            sourceVersion: '2026-10',
        );
    }
}
