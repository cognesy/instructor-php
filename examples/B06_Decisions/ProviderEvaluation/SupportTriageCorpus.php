<?php

declare(strict_types=1);

namespace Cognesy\Examples\DecisionEvaluation;

use Cognesy\Polyglot\Decision\Collections\ChoiceOptions;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Collections\ScoreLevels;
use Cognesy\Polyglot\Decision\Data\ChoiceOption;
use Cognesy\Polyglot\Decision\Data\NoulCriteria;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;

final class SupportTriageCorpus
{
    public const string ID = 'support-triage';

    public const string VERSION = '2026-09-20';

    /** @return list<SupportTriageCase> */
    public static function cases(): array
    {
        return [
            new SupportTriageCase(
                id: 'duplicate-charge-refund-today',
                input: 'I was charged twice for order A-104. Refund the duplicate charge today.',
                refundRequested: true,
                department: 'billing',
                urgency: 2,
            ),
            new SupportTriageCase(
                id: 'invoice-copy-this-week',
                input: 'Please send a copy of our August invoice before Friday. No refund is needed.',
                refundRequested: false,
                department: 'billing',
                urgency: 1,
            ),
            new SupportTriageCase(
                id: 'checkout-payment-blocked',
                input: 'Every company card is declined at checkout and we must place the order today.',
                refundRequested: false,
                department: 'billing',
                urgency: 2,
            ),
            new SupportTriageCase(
                id: 'refund-next-week',
                input: 'Please refund the unused add-on. Processing it any time next week is fine.',
                refundRequested: true,
                department: 'billing',
                urgency: 1,
            ),
            new SupportTriageCase(
                id: 'production-api-outage',
                input: 'Our production API has returned 503 for every request since 09:10 UTC. We are blocked now.',
                refundRequested: false,
                department: 'technical',
                urgency: 2,
            ),
            new SupportTriageCase(
                id: 'sso-setup-this-week',
                input: 'Help us configure SAML SSO in staging before our test session this Thursday.',
                refundRequested: false,
                department: 'technical',
                urgency: 1,
            ),
            new SupportTriageCase(
                id: 'sdk-question-can-wait',
                input: 'Is there a PHP example for cursor pagination? This is for a future prototype and can wait.',
                refundRequested: false,
                department: 'technical',
                urgency: 0,
            ),
            new SupportTriageCase(
                id: 'minor-dashboard-bug',
                input: 'The staging dashboard icon is misaligned. There is no outage and no deadline.',
                refundRequested: false,
                department: 'technical',
                urgency: 0,
            ),
            new SupportTriageCase(
                id: 'enterprise-pricing-next-month',
                input: 'What does the enterprise plan cost for 500 seats? We are budgeting for next month.',
                refundRequested: false,
                department: 'sales',
                urgency: 0,
            ),
            new SupportTriageCase(
                id: 'annual-quote-this-week',
                input: 'Please send an annual contract quote for 80 seats before our review on Friday.',
                refundRequested: false,
                department: 'sales',
                urgency: 1,
            ),
            new SupportTriageCase(
                id: 'upgrade-demo-today',
                input: 'We need a Pro plan demo for the buying committee this afternoon. Can sales call us today?',
                refundRequested: false,
                department: 'sales',
                urgency: 2,
            ),
            new SupportTriageCase(
                id: 'cancel-and-refund-today',
                input: 'Cancel the renewal and return the payment before it settles today.',
                refundRequested: true,
                department: 'billing',
                urgency: 2,
            ),
        ];
    }

    public static function questions(): Questions
    {
        return Questions::of(
            new Noul(
                id: 'refund_requested',
                instructions: 'Does the customer explicitly ask for money to be returned?',
                criteria: new NoulCriteria(
                    true: 'The customer explicitly asks for a refund or payment reversal.',
                    false: 'The customer does not ask for money to be returned.',
                ),
            ),
            new Choice(
                id: 'department',
                options: ChoiceOptions::of(
                    new ChoiceOption('billing', 'Payments, charges, invoices, cancellations, and refunds.'),
                    new ChoiceOption('technical', 'Software behavior, configuration, integrations, and incidents.'),
                    new ChoiceOption('sales', 'Plans, pricing, quotes, demos, and purchases.'),
                ),
                instructions: 'Which one department should own the next response?',
            ),
            new Score(
                id: 'urgency',
                levels: ScoreLevels::of(
                    'Can wait: no deadline or action needed this week.',
                    'This week: a stated deadline within several days.',
                    'Today: blocked, active outage, or an explicit same-day deadline.',
                ),
                instructions: 'How urgently should the message be handled?',
            ),
        );
    }
}
