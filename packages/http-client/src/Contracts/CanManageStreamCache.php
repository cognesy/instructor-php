<?php declare(strict_types=1);

namespace Cognesy\Http\Contracts;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Enums\StreamCachePolicy;

interface CanManageStreamCache
{
    public function manage(HttpResponse $response, StreamCachePolicy $policy): HttpResponse;
}

