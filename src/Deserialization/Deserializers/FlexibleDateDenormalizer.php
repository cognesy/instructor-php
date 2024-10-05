<?php
namespace Cognesy\Instructor\Deserialization\Deserializers;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

class FlexibleDateDenormalizer implements DenormalizerInterface
{
    public function denormalize($data, string $type, string $format = null, array $context = []): mixed
    {
        if (!is_string($data)) {
            throw new NotNormalizableValueException('The data is not a string, it cannot be converted to a DateTime.');
        }

        $output = $this->parseDate($data);

        if ($output === false) {
            throw new NotNormalizableValueException('The date string could not be parsed.');
        }

        return $output;
    }

    public function getSupportedTypes(?string $format): array {
        return [
            DateTimeInterface::class => true,
            DateTimeImmutable::class => true,
            DateTime::class => true,
        ];
    }

    public function supportsDenormalization($data, string $type, string $format = null, array $context = []): bool {
        return in_array($type, [
            DateTimeInterface::class,
            DateTimeImmutable::class,
            DateTime::class,
        ]);
    }

    private function parseDate(string $dateString) : DateTimeImmutable|DateTime|false {
        $formats = [
            'Y-m-d\TH:i:s.uP', // ISO8601 with microseconds
            'Y-m-d\TH:i:sP',   // ISO8601
            'Y-m-d H:i:s',     // MySQL datetime
            'Y-m-d',           // Simple date
            'd/m/Y',           // European format
            'm/d/Y',           // US format
            // Add more formats as needed
        ];

        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $dateString);
            if ($date !== false) {
                return $date;
            }
        }

        // If none of the formats work, try a more flexible approach
        return date_create($dateString);
    }
}