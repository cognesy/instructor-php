<?php

declare(strict_types=1);

namespace Cognesy\Tell\Tests\Support;

final class RequestRecorder
{
    /** @var list<array<int, mixed>> */
    public array $requests = [];
}
