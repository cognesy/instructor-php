<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\Support;

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

final class RequestMessages
{
    public static function forMapping(
        InferenceRequest $request,
        bool $supportsAlternatingRoles,
    ): Messages {
        return match ($supportsAlternatingRoles) {
            false => $request->messages()->toMergedPerRole(),
            true => $request->messages(),
        };
    }

    public static function exceptRoles(Messages $messages, array $roles): Messages
    {
        return $messages->exceptRoles($roles);
    }

    public static function textForRoles(
        Messages $messages,
        array $roles,
        string $separator = "\n\n",
    ): string {
        $fragments = [];
        foreach ($messages->forRoles($roles)->messageList()->all() as $message) {
            foreach ($message->content()->partsList()->all() as $part) {
                if (! $part->hasText() || $part->isEmpty()) {
                    continue;
                }
                $fragments[] = $part->toString();
            }
        }

        return implode($separator, $fragments);
    }
}
