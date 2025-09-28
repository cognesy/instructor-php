<?php declare(strict_types=1);

namespace Cognesy\Experimental\ModPredict\Optimize;

use Cognesy\Experimental\Signature\Signature;

final class SignatureUtil
{
    public static function id(Signature $signature): string
    {
        $data = $signature->toArray();
        // ignore instructions (not part of contract); use structural parts
        $payload = [
            'shortSignature' => $data['shortSignature'] ?? '',
            'fullSignature' => $data['fullSignature'] ?? '',
            'input' => $data['input'] ?? [],
            'output' => $data['output'] ?? [],
        ];
        return hash('sha256', json_encode($payload));
    }
}

