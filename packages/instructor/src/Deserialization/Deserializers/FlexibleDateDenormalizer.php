<?php declare(strict_types=1);
namespace Cognesy\Instructor\Deserialization\Deserializers;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class FlexibleDateDenormalizer implements DenormalizerInterface
{
    #[\Override]
    public function denormalize($data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (!is_string($data)) {
            throw new NotNormalizableValueException('The data is not a string, it cannot be converted to a DateTime.');
        }

        $output = $this->parseDate($data, $type);

        if ($output === false) {
            throw new NotNormalizableValueException('The date string could not be parsed.');
        }

        return $output;
    }

    /**
     * @return array<class-string, bool>
     */
    public function getSupportedTypes(?string $format): array {
        return [
            DateTimeInterface::class => true,
            DateTimeImmutable::class => true,
            DateTime::class => true,
        ];
    }

    #[\Override]
    public function supportsDenormalization($data, string $type, ?string $format = null, array $context = []): bool {
        return in_array($type, [
            DateTimeInterface::class,
            DateTimeImmutable::class,
            DateTime::class,
        ]);
    }

    private function parseDate(string $dateString, string $targetType) : DateTimeImmutable|DateTime|false {
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
                return $this->convertToTargetType($date, $targetType);
            }
        }

        // If none of the formats work, try a more flexible approach
        $date = date_create($dateString);
        if ($date !== false) {
            return $this->convertToTargetType($date, $targetType);
        }

        return false;
    }

    private function convertToTargetType(DateTime $date, string $targetType): DateTimeImmutable|DateTime {
        if ($targetType === DateTimeImmutable::class || $targetType === DateTimeInterface::class) {
            return DateTimeImmutable::createFromMutable($date);
        }
        
        return $date;
    }
}