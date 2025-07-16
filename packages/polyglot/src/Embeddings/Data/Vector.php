<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Embeddings\Data;

/**
 * Class Vector
 *
 * Represents an embedding - vector of floating point values
 */
class Vector
{
    public const METRIC_COSINE = 'cosine';
    public const METRIC_EUCLIDEAN = 'euclidean';
    public const METRIC_DOT_PRODUCT = 'dot_product';

    public function __construct(
        /** @var float[] */
        private array $values,
        private int|string $id = 0,
    ) {}

    /**
     * Get the vector values
     * @return float[]
     */
    public function values() : array {
        return $this->values;
    }

    /**
     * Get the vector ID
     * @return int|string
     */
    public function id() : int|string {
        return $this->id;
    }

    /**
     * Compare this vector to another vector using a metric
     * @param Vector $vector
     * @param string $metric
     * @return float
     */
    public function compareTo(Vector $vector, string $metric) : float {
        return match ($metric) {
            self::METRIC_COSINE => self::cosineSimilarity($this->values, $vector->values),
            self::METRIC_EUCLIDEAN => self::euclideanDistance($this->values, $vector->values),
            self::METRIC_DOT_PRODUCT => self::dotProduct($this->values, $vector->values),
            default => throw new \InvalidArgumentException("Unknown metric: $metric")
        };
    }

    /**
     * Calculate the cosine similarity between two vectors
     * @param float[] $v1
     * @param float[] $v2
     */
    public static function cosineSimilarity(array $v1, array $v2) : float {
        if (count($v1) !== count($v2)) {
            throw new \InvalidArgumentException("Vectors must be of the same length");
        }

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

    /**
     * Calculate the Euclidean distance between two vectors
     * @param float[] $v1
     * @param float[] $v2
     */
    public static function euclideanDistance(array $v1, array $v2) : float {
        if (count($v1) !== count($v2)) {
            throw new \InvalidArgumentException("Vectors must be of the same length");
        }

        $sum = 0;
        $count = count($v1);
        for ($i = 0; $i < $count; $i++) {
            $sum += ($v1[$i] - $v2[$i]) ** 2;
        }
        return sqrt($sum);
    }

    /**
     * Calculate the dot product between two vectors
     * @param float[] $v1
     * @param float[] $v2
     */
    public static function dotProduct(array $v1, array $v2) : float {
        if (count($v1) !== count($v2)) {
            throw new \InvalidArgumentException("Vectors must be of the same length");
        }

        $sum = 0;
        $count = count($v1);
        for ($i = 0; $i < $count; $i++) {
            $sum += $v1[$i] * $v2[$i];
        }
        return $sum;
    }

    public function toArray() : array {
        return [
            'id' => $this->id,
            'values' => $this->values,
        ];
    }
}