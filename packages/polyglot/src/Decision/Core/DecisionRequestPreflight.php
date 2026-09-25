<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Core;

use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Models\DecisionPrimitiveSupport;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;
use InvalidArgumentException;

final readonly class DecisionRequestPreflight
{
    public static function assertSupported(DecisionRequest $request): void
    {
        $capabilities = $request->modelProfile()?->capabilities;
        if ($capabilities === null) {
            return;
        }

        foreach ($request->questions()->all() as $question) {
            [$primitive, $support] = match (true) {
                $question instanceof Choice => ['Choice', $capabilities->choice],
                $question instanceof Noul => ['Noul', $capabilities->noul],
                $question instanceof Score => ['Score', $capabilities->score],
            };
            self::assertPrimitive($primitive, $support);
        }
    }

    private static function assertPrimitive(
        string $primitive,
        DecisionPrimitiveSupport $support,
    ): void {
        if (! $support->isUnsupported()) {
            return;
        }

        throw new InvalidArgumentException("Decision model does not support {$primitive} questions.");
    }
}
