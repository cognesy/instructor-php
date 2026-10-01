<?php

declare(strict_types=1);

namespace Examples\Retrieval\SupportReplyWithSources;

use Cognesy\Retrieval\Context\AssembledContext;
use Cognesy\Retrieval\Context\ContextBudget;
use Cognesy\Retrieval\Data\SearchHits;
use Cognesy\Retrieval\Indexing\Data\IndexingReport;

final class WalkthroughOutput
{
    public static function section(string $title): void {
        echo PHP_EOL . '=== ' . $title . ' ===' . PHP_EOL . PHP_EOL;
    }

    public static function text(string $text): void {
        echo wordwrap($text, 88) . PHP_EOL;
    }

    public static function inputs(bool $live): void {
        self::section(match (true) {
            !$live => 'DETERMINISTIC SUPPORT WORKFLOW',
            self::replaying() => 'RECORDED PROVIDER SUPPORT WORKFLOW',
            default => 'LIVE SUPPORT WORKFLOW',
        });
        self::text(match (true) {
            !$live => 'Fixture vectors and scripted model responses exercise the real application code.',
            self::replaying() => 'Replaying recorded OpenAI embeddings and generation; no provider calls are made.',
            default => 'Real OpenAI embeddings and generation on a fictional policy corpus.',
        });
        self::section('1. What the customer asks');
        self::text(KnowledgeBase::QUESTION);
        echo PHP_EOL;
        self::text('The application supplies these trusted account facts:');
        self::text(KnowledgeBase::ACCOUNT);
        self::text('The account facts supply the 5-day renewal age; retrieval chooses the policy evidence.');
        self::text('The reply must explain refund review and how to stop the next renewal.');
    }

    public static function indexed(IndexingReport $report, string $space, string $model): void {
        self::section('2. Turn policy documents into searchable vectors');
        self::text("Indexed {$report->indexed} documents from {$report->sources} sources in {$report->batches} embedding batches.");
        self::text('Each short policy stays in one piece. Its text becomes an embedding; its title, knowledge set, and version remain attached to the stored record.');
        self::text('Store: InMemoryStore. Embedding model: ' . match ($model) {
            '' => 'fixture vectors (3 dimensions)',
            default => $model,
        });
        self::text('Embedding space: ' . $space);
    }

    public static function search(SearchHits $unfiltered, SearchHits $approved): void {
        self::section('3. Similarity ranking and policy eligibility');
        self::text('The question is embedded and compared with document vectors using cosine similarity: dot(question, document) / (length(question) * length(document)). Higher scores rank first; they are not probabilities of correctness.');
        echo PHP_EOL;
        self::text('Search across the whole corpus:');
        self::hits($unfiltered);
        echo PHP_EOL;
        self::text('Now PHP requires knowledge_set = ' . KnowledgeBase::APPROVED);
        self::text('The store filters eligibility before ranking. This excludes archived and Enterprise policies even if their text is similar.');
        self::hits($approved);
    }

    public static function context(AssembledContext $context, ContextBudget $budget): void {
        self::section('4. Build the evidence packet for generation');
        self::text("Evidence usage: {$context->bytes}/{$budget->maxBytes} bytes; {$context->tokens}/{$budget->maxTokens} local tokens; {$context->count()}/{$budget->maxEvidence} sources.");
        self::text("Excerpt cap: {$budget->maxExcerptBytes} bytes per source. Omitted hits: {$context->omitted}.");
        self::text('The normal packet keeps the first 2 policy hits and omits the third because maxEvidence is 2. Token counts use the bundled r50k_base tokenizer and cover evidence only; the full model prompt has additional inputs.');
        echo PHP_EOL;
        self::text('Actual evidence passed to the model:');
        self::text($context->text);
    }

    public static function generation(bool $live): void {
        self::section('5. Ask for a typed reply draft');
        self::text(match (true) {
            !$live => 'Passing the same inputs through StructuredOutput with a scripted response driver.',
            self::replaying() => 'Using the recorded gpt-4o-mini response to the account facts, customer message, and evidence packet.',
            default => 'Calling gpt-4o-mini with the account facts, customer message, and evidence packet.',
        });
        self::text('ReplyDraft requires body, disposition, and citations. The instructions request source labels and forbid promises that a refund or cancellation has already happened.');
    }

    public static function draft(ReplyDraft $draft, AssembledContext $context, ?string $rejection): void {
        self::section('6. Check the draft and present it for support review');
        self::text('Disposition: ' . $draft->disposition->value);
        self::text(match ($rejection) {
            null => 'Citation checks passed: every listed source exists and inline labels match the list.',
            default => 'Draft blocked: ' . $rejection,
        });
        echo PHP_EOL;
        self::text(match ($rejection) {
            null => 'CUSTOMER-FACING REPLY DRAFT',
            default => 'BLOCKED REPLY CANDIDATE',
        });
        self::text(str_repeat('-', 40));
        self::text($draft->body);
        self::text(str_repeat('-', 40));
        echo PHP_EOL;
        self::text('Source references shown to the reviewer:');
        foreach ($context->citations() as $label => $source) {
            self::text("  [{$label}] {$source->metadata['title']} ({$source->id}, version {$source->metadata['_source_version']})");
        }
        echo PHP_EOL;
        self::text(match ($rejection) {
            null => 'The application would offer this text to a support reviewer before sending. Billing still needs to review refund approval.',
            default => 'The application must handle this failed draft before it can be offered as a reviewable reply.',
        });
        self::text('This example performs no refund, cancellation, or email action.');
        self::text('Citation membership does not establish that every statement is supported by its source.');
    }

    private static function replaying(): bool {
        return getenv('INSTRUCTOR_EXAMPLES_HTTP') === 'replay';
    }

    private static function hits(SearchHits $hits): void {
        foreach ($hits as $hit) {
            $eligibility = match ($hit->metadata['knowledge_set'] === KnowledgeBase::APPROVED) {
                true => 'current Standard policy: eligible',
                false => 'different knowledge set: excluded from the approved search',
            };
            self::text(sprintf('  %d. %s | cosine %.4f', $hit->rank, $hit->metadata['title'], $hit->score->value));
            self::text("     {$hit->id}; {$eligibility}");
        }
    }
}
