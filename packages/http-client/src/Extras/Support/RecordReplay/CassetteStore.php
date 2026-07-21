<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;

/**
 * Persistence boundary used by the public record/replay middleware.
 *
 * The store owns recording and replay materialization. This keeps filesystem,
 * database, and proxy-backed cassettes out of the middleware API.
 */
interface CassetteStore
{
    public function record(HttpRequest $request, HttpResponse $response): HttpResponse;

    public function replay(HttpRequest $request): ?HttpResponse;
}
