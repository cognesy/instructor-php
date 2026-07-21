<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay;

use Cognesy\Http\Extras\Support\RecordReplay\Matching\ExactHashMatcher;
use Cognesy\Http\Extras\Support\RecordReplay\Matching\RequestMatcher;
use Cognesy\Http\Extras\Support\RecordReplay\Redaction\DefaultRequestRedactor;

final readonly class RecordReplayPolicy
{
    public function __construct(
        public RequestMatcher $matcher = new ExactHashMatcher(),
        public FixtureSanitizer $sanitizer = new DefaultRequestRedactor(),
        public ReplayMissPolicy $onMissing = ReplayMissPolicy::Fail,
    ) {
    }
}
