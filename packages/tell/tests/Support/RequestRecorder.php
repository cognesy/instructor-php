<?php

declare(strict_types=1);

namespace Cognesy\Tell\Tests\Support;

use Cognesy\Messages\ContentParts;

final class RequestRecorder
{
    /** @var list<array<int, mixed>> */
    public array $requests = [];

    /** @return list<array{role: string, content: string}> */
    public function textProjection(int $request): array {
        return array_map(
            static fn (array $message): array => [
                'role' => $message['role'],
                'content' => ContentParts::fromArray($message['parts'])->toString(),
            ],
            $this->requests[$request],
        );
    }
}
