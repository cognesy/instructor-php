<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\Tbd;

use Cognesy\Utils\Uuid;

class TbdIdFactory
{
    public function generate(?string $seed = null): string {
        if ($seed === null || $seed === '') {
            return Uuid::uuid4();
        }
        // Deterministic UUID-like string derived from seed (not RFC compliant but stable)
        $hash = sha1($seed);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(substr($hash, 0, 32), 4));
    }
}
