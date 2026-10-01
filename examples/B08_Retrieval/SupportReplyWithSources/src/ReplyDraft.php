<?php

declare(strict_types=1);

namespace Examples\Retrieval\SupportReplyWithSources;

use Cognesy\Schema\Attributes\Description;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReplyDraft
{
    /** @param list<string> $citations */
    public function __construct(
        #[Assert\NotBlank]
        #[Description('Customer-facing reply addressed directly to the customer. Include inline labels [S1], [S2] beside policy statements. Do not summarize the customer request in third person.')]
        public string $body,
        public ReplyDisposition $disposition,
        #[Description('Source labels used inline in the body, without brackets, for example ["S1", "S2"].')]
        public array $citations,
    ) {}
}
