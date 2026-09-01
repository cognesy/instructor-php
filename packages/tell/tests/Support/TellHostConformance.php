<?php

declare(strict_types=1);

namespace Cognesy\Tell\Tests\Support;

use Closure;
use Cognesy\Tell\Data\TellRequest;
use RuntimeException;

/** Shared behavioral contract for Tell composition hosts. */
final readonly class TellHostConformance
{
    /** @param Closure(string): TellHostConformanceHarness $boot */
    public function __construct(private Closure $boot) {}

    public function verify(string $directory): void {
        $first = ($this->boot)('first host');
        $second = ($this->boot)('second host');

        try {
            if ($first->runner() === $second->runner()) {
                throw new RuntimeException('Separate hosts shared a runner instance.');
            }
            $firstRequest = TellRequest::prompt('first run')->withDirectory($directory);
            $secondRequest = TellRequest::prompt('second run')->withDirectory($directory);

            $firstResult = $first->runner()->run($firstRequest);
            $repeatedResult = $first->runner()->run($secondRequest);
            $secondResult = $second->runner()->run($firstRequest);

            if (trim($firstResult->text()) !== 'first host') {
                throw new RuntimeException('The first host did not use its selected provider.');
            }
            if (trim($repeatedResult->text()) !== 'first host') {
                throw new RuntimeException('A host could not create more than one execution.');
            }
            if (trim($secondResult->text()) !== 'second host') {
                throw new RuntimeException('Provider selection leaked between hosts.');
            }

            $first->dispose();
            if (!$first->rejectsAccessAfterDisposal()) {
                throw new RuntimeException('A disposed host remained accessible.');
            }

            $afterDisposal = $second->runner()->run($secondRequest);
            if (trim($afterDisposal->text()) !== 'second host') {
                throw new RuntimeException('Disposing one host affected another host.');
            }
        } finally {
            $first->dispose();
            $second->dispose();
        }
    }
}
