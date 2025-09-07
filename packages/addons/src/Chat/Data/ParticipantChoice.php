<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Data;

readonly class ParticipantChoice
{
    public function __construct(
        public string $participantName,
        public ?string $reason = null,
    ) {}
}