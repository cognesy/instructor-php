<?php

declare(strict_types=1);

namespace Examples\Retrieval\SupportReplyWithSources;

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Retrieval\Context\AssembledContext;
use Cognesy\Retrieval\Context\ContextAssembler;
use Cognesy\Retrieval\Context\ContextBudget;
use Cognesy\Retrieval\Data\SearchHit;
use Cognesy\Retrieval\Data\SearchHits;
use Cognesy\Retrieval\Drivers\InMemory\InMemoryStore;
use Cognesy\Retrieval\Filter\MetadataEquals;
use Cognesy\Retrieval\Indexing\DocumentIndexer;
use Cognesy\Retrieval\Indexing\DocumentProcessor;
use Cognesy\Retrieval\Retrieval;
use Cognesy\Retrieval\SemanticRetriever;
use Cognesy\Retrieval\Vectorization\SemanticQueryVectorizer;
use Cognesy\Retrieval\Vectorization\Vectorizer;
use Cognesy\Utils\Tokenization\Drivers\Gpt3TokenizerDriver;
use RuntimeException;

final class SupportReplyProof
{
    public static function run(bool $live): void {
        if ($live) {
            self::runLive();
        }
        self::runFixture(explain: !$live);
    }

    private static function runFixture(bool $explain): void {
        if ($explain) {
            WalkthroughOutput::inputs(live: false);
        }
        $events = new EventDispatcher('example.support-reply');
        $retrieval = self::index(new FixtureEmbeddings($events), 'fixture:support:v1', explain: $explain);
        $unfiltered = (new SemanticRetriever($retrieval, embeddingSpace: 'fixture:support:v1'))
            ->retrieve(KnowledgeBase::QUESTION, 2)->get();
        self::check(self::ids($unfiltered) === 'archived-refund, enterprise-refund', 'Fixture must force ineligible policies first.');

        $hits = self::eligibleHits($retrieval, 'fixture:support:v1');
        self::check(self::ids($hits) === 'standard-refund, cancel-renewal, payment-errors', 'Expected eligible fixture ranking.');
        $context = self::assembler()->assemble($hits, self::budget());
        self::verifySources($context);
        if ($explain) {
            WalkthroughOutput::search($unfiltered, $hits);
            WalkthroughOutput::context($context, self::budget());
            WalkthroughOutput::generation(live: false);
        }

        $driver = new RecordingReplyDriver(new ReplyDraft(
            'Your renewal was 5 days ago, so you can submit a refund request for billing review within the 7-day window. Approval depends on usage and billing checks [S1]. To stop the next renewal, choose Settings > Billing > Subscription > Cancel renewal. Access continues through the paid period; cancellation does not automatically refund this renewal [S2].',
            ReplyDisposition::ReadyForReview,
            ['S1', 'S2'],
        ));
        $drafting = self::fixtureDrafting($driver);
        $draft = $drafting->draft(KnowledgeBase::QUESTION, KnowledgeBase::ACCOUNT, $context);
        self::check($drafting->rejection($draft, $context) === null, 'Valid fixture draft must pass citation checks.');
        self::check($driver->requests === 1, 'Expected one generation request.');
        $prompt = $driver->lastMessages->toString();
        self::check(str_contains($prompt, $context->text), 'Generation must receive the assembled evidence.');
        self::check(!str_contains($prompt, 'ARCHIVED REFUND') && !str_contains($prompt, 'ENTERPRISE REFUND'), 'Ineligible content must stay out of the prompt.');
        if ($explain) {
            WalkthroughOutput::draft($draft, $context, $drafting->rejection($draft, $context));
        }

        WalkthroughOutput::section('DETERMINISTIC BOUNDARY CHECKS');
        WalkthroughOutput::text('These forced cases use fixture vectors and responses, make no provider calls, and test application mechanics.');
        WalkthroughOutput::text('PASS: unfiltered fixture ranking chose archived/Enterprise policies; approved filtering admitted only current Standard documents into the prompt.');
        self::verifyBudgets($hits);
        self::verifyHandoff($retrieval, $drafting, $driver);
        self::verifyRejections($context);
        WalkthroughOutput::text('PASS: all deterministic retrieval, prompt, budget, handoff, and citation checks.');
    }

    private static function index(CanCreateEmbeddings $embeddings, string $space, string $model = '', bool $explain = true): Retrieval {
        $store = new InMemoryStore();
        $vectorizer = new Vectorizer($embeddings, $space, $model);
        $report = (new DocumentIndexer(new DocumentProcessor(
            store: $store,
            vectorizer: $vectorizer,
            events: new EventDispatcher('example.support-index'),
        )))
            ->index((new KnowledgeBase())->documents());
        self::check($report->indexed === 6, 'All 6 source documents must be indexed.');
        if ($explain) {
            WalkthroughOutput::indexed($report, $space, $model);
        }

        return Retrieval::fromStore($store, queryPreparer: new SemanticQueryVectorizer($vectorizer));
    }

    private static function eligibleHits(Retrieval $retrieval, string $space): SearchHits {
        $hits = (new SemanticRetriever(
            $retrieval,
            embeddingSpace: $space,
            filter: new MetadataEquals('knowledge_set', KnowledgeBase::APPROVED),
        ))->retrieve(KnowledgeBase::QUESTION, 3)->get();

        foreach ($hits as $hit) {
            self::check(($hit->metadata['knowledge_set'] ?? '') === KnowledgeBase::APPROVED, 'Every hit must belong to the approved knowledge set.');
        }

        return $hits;
    }

    private static function assembler(): ContextAssembler {
        return new ContextAssembler(
            tokenizer: new Gpt3TokenizerDriver(),
            metadataKeys: ['title', '_source_version', 'knowledge_set'],
        );
    }

    private static function budget(): ContextBudget {
        return new ContextBudget(maxBytes: 2_500, maxTokens: 650, maxEvidence: 2, maxExcerptBytes: 1_200);
    }

    private static function fixtureDrafting(RecordingReplyDriver $driver): SupportReplyDrafting {
        $runtime = StructuredOutputRuntime::fromProvider(LLMProvider::new()->withDriver($driver))
            ->withOutputMode(OutputMode::Json)
            ->withMaxRetries(0);

        return new SupportReplyDrafting(new StructuredOutput($runtime));
    }

    private static function verifySources(AssembledContext $context): void {
        $ids = array_column($context->evidence, 'id');
        sort($ids);
        self::check($ids === ['cancel-renewal', 'standard-refund'], 'Both answer-bearing policies must survive context assembly.');
        self::check($context->count() === 2, 'Only 2 sources should enter the normal prompt.');
        foreach ($context->citations() as $label => $source) {
            self::check(str_contains($context->text, "[{$label}] {$source->content}"), 'Citation must map to the exact included excerpt.');
            self::check(($source->metadata['_source_version'] ?? '') === '2026-10', 'Source version must remain available for review.');
        }
    }

    private static function verifyBudgets(SearchHits $hits): void {
        $longHits = new SearchHits(array_map(
            static fn (SearchHit $hit): SearchHit => new SearchHit(
                $hit->id,
                $hit->rank,
                $hit->score,
                $hit->metadata,
                str_repeat($hit->content ?? '', 30),
            ),
            $hits->all(),
        ));
        $budget = new ContextBudget(maxBytes: 220, maxTokens: 60, maxEvidence: 2, maxExcerptBytes: 120);
        $context = self::assembler()->assemble($longHits, $budget);
        self::check($context->count() > 0 && $context->count() <= 2, 'Bounded context must contain at most 2 usable sources.');
        self::check($context->bytes <= 220 && $context->tokens <= 60, 'Combined byte/token bounds must hold.');
        self::check($context->omitted > 0, 'At least one source must be omitted.');
        foreach ($context->evidence as $source) {
            self::check(strlen($source->content) <= 120, 'Every included excerpt must respect its byte cap.');
            self::check(strlen($source->content) < strlen($hits->first()->content ?? ''), 'Long evidence must actually be truncated.');
        }
        WalkthroughOutput::text("PASS: repeating documents 30 times still yields {$context->bytes}/220 bytes, {$context->tokens}/60 local tokens, and {$context->count()}/2 sources. Every excerpt is at most 120 bytes; {$context->omitted} hit is omitted.");

        $byteBound = self::assembler()->assemble($longHits, new ContextBudget(maxBytes: 80, maxTokens: 10_000, maxExcerptBytes: 10_000));
        $tokenBound = self::assembler()->assemble($longHits, new ContextBudget(maxBytes: 10_000, maxTokens: 10, maxExcerptBytes: 10_000));
        self::check($byteBound->bytes <= 80 && $byteBound->bytes > 0, 'Independent byte cap must bind.');
        self::check($tokenBound->tokens <= 10 && $tokenBound->tokens > 0, 'Independent tokenizer cap must bind.');
        WalkthroughOutput::text("PASS: separate byte-only and token-only cases use {$byteBound->bytes}/80 bytes and {$tokenBound->tokens}/10 local tokens.");
    }

    private static function verifyHandoff(Retrieval $retrieval, SupportReplyDrafting $drafting, RecordingReplyDriver $driver): void {
        $hits = (new SemanticRetriever(
            $retrieval,
            embeddingSpace: 'fixture:support:v1',
            filter: new MetadataEquals('knowledge_set', 'new-product:approved:2026-10'),
        ))->retrieve(KnowledgeBase::QUESTION, 3)->get();
        $context = self::assembler()->assemble($hits, self::budget());
        $before = $driver->requests;
        $draft = $drafting->draft(KnowledgeBase::QUESTION, KnowledgeBase::ACCOUNT, $context);
        self::check($context->count() === 0, 'Missing approved corpus must yield empty evidence.');
        self::check($draft->disposition === ReplyDisposition::InsufficientEvidence, 'Missing evidence must produce a handoff.');
        self::check($driver->requests === $before, 'Handoff must make zero additional generation calls.');
        WalkthroughOutput::text('PASS: a missing approved corpus returns insufficient_evidence and makes 0 additional generation calls; PHP routes the request to human review.');
    }

    private static function verifyRejections(AssembledContext $context): void {
        $driver = new RecordingReplyDriver(new ReplyDraft('Your refund is approved [S99].', ReplyDisposition::ReadyForReview, ['S99']));
        $drafting = self::fixtureDrafting($driver);
        $draft = $drafting->draft(KnowledgeBase::QUESTION, KnowledgeBase::ACCOUNT, $context);
        $reason = $drafting->rejection($draft, $context);
        self::check($reason === 'Unknown citation: S99.', 'A typed but invented reference must be rejected.');
        WalkthroughOutput::text('PASS: a typed draft claiming approval with [S99] is blocked. ' . ($reason ?? ''));
        self::check($drafting->rejection(new ReplyDraft('Answer [S99].', ReplyDisposition::ReadyForReview, ['S1']), $context) !== null, 'Undeclared inline citation must be rejected.');
        self::check($drafting->rejection(new ReplyDraft('Answer.', ReplyDisposition::ReadyForReview, []), $context) !== null, 'An uncited ready draft must be rejected.');
        WalkthroughOutput::text('PASS: mismatched inline labels and drafts with no citations are also blocked.');

        $unsupported = new ReplyDraft('Your refund has already been approved [S1].', ReplyDisposition::ReadyForReview, ['S1']);
        self::check($drafting->rejection($unsupported, $context) === null, 'Membership checks deliberately do not verify factual support.');
        WalkthroughOutput::text('LIMIT: "Your refund has already been approved [S1]" passes citation membership because S1 exists. That claim is unsupported; factual review is still required.');
    }

    private static function runLive(): void {
        WalkthroughOutput::inputs(live: true);
        $space = 'openai:text-embedding-3-small:v1';
        $retrieval = self::index(Embeddings::using('openai'), $space, 'text-embedding-3-small');
        $unfiltered = (new SemanticRetriever($retrieval, embeddingSpace: $space))->retrieve(KnowledgeBase::QUESTION, 3)->get();
        $hits = self::eligibleHits($retrieval, $space);
        WalkthroughOutput::search($unfiltered, $hits);
        $context = self::assembler()->assemble($hits, self::budget());
        self::verifySources($context);
        WalkthroughOutput::context($context, self::budget());
        WalkthroughOutput::generation(live: true);
        $runtime = StructuredOutputRuntime::fromProvider(LLMProvider::using('openai'))
            ->withOutputMode(OutputMode::Json)
            ->withMaxRetries(0);
        $drafting = new SupportReplyDrafting(new StructuredOutput($runtime));
        $draft = $drafting->draft(KnowledgeBase::QUESTION, KnowledgeBase::ACCOUNT, $context);
        $reason = $drafting->rejection($draft, $context);
        WalkthroughOutput::draft($draft, $context, $reason);
        self::check($reason === null, 'Live draft must be reviewable with valid source labels: ' . ($reason ?? ''));
        WalkthroughOutput::text('PASS: live retrieval and typed generation; the candidate passed citation checks.');
    }

    private static function ids(SearchHits $hits): string {
        return implode(', ', array_map(static fn (SearchHit $hit): string => (string) $hit->id, $hits->all()));
    }

    private static function check(bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException('Proof failed: ' . $message);
        }
    }
}
