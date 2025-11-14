<?php declare(strict_types=1);

namespace Cognesy\Http\Contracts;

use Cognesy\Http\Data\HttpResponse;

interface CanAdaptHttpResponse
{
    public function toHttpResponse() : HttpResponse;
}
