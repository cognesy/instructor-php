<?php

namespace Cognesy\Instructor\Extras\Embeddings;

class Vector
{
    public static function cosineSimilarity(array $v1, array $v2) : float {
        $dotProduct = 0.0;
        $magnitudeV1 = 0.0;
        $magnitudeV2 = 0.0;
        $count = count($v1);
        for ($i = 0; $i < $count; $i++) {
            $dotProduct += $v1[$i] * $v2[$i];
            $magnitudeV1 += $v1[$i] ** 2;
            $magnitudeV2 += $v2[$i] ** 2;
        }
        $magnitudeV1 = sqrt($magnitudeV1);
        $magnitudeV2 = sqrt($magnitudeV2);
        return $dotProduct / ($magnitudeV1 * $magnitudeV2);
    }

//    private function cosineSimilarity(array $vec1, array $vec2) : float {
//        $dotProduct = array_sum(array_map(fn($a, $b) => $a * $b, $vec1, $vec2));
//        $magnitude1 = sqrt(array_sum(array_map(fn($a) => $a * $a, $vec1)));
//        $magnitude2 = sqrt(array_sum(array_map(fn($b) => $b * $b, $vec2)));
//        if ($magnitude1 * $magnitude2 == 0) {
//            return 0;
//        }
//        return $dotProduct / ($magnitude1 * $magnitude2);
//    }

    public static function euclideanDistance(array $v1, array $v2) : float {
        $sum = 0;
        $count = count($v1);
        for ($i = 0; $i < $count; $i++) {
            $sum += ($v1[$i] - $v2[$i]) ** 2;
        }
        return sqrt($sum);
    }

    public static function dotProduct(array $v1, array $v2) : float {
        $sum = 0;
        $count = count($v1);
        for ($i = 0; $i < $count; $i++) {
            $sum += $v1[$i] * $v2[$i];
        }
        return $sum;
    }
}