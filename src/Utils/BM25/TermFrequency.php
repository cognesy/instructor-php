<?php
namespace Cognesy\Instructor\Utils\BM25;

// Source: https://gist.github.com/jtejido/

use JetBrains\PhpStorm\Deprecated;
use NlpTools\Analysis\FreqDist;

#[Deprecated('Not used - may be removed in the future.')]
class TermFrequency extends FreqDist
{

    const FREQUENCY_MODE = 1;
    const BOOLEAN_MODE = 2;
    const LOGARITHMIC_MODE = 3;
    const AUGMENTED_MODE = 4;
    const COUNT_MODE = 5;

    protected $mode;

    public function __construct(array $tokens, $mode=self::FREQUENCY_MODE)
    {
        parent::__construct($tokens);
        $this->mode = $mode;
    }

    public function getTf($term)
    {
        $count = $this->getTotalByToken($term);
        if (!$count) {
            return 0;
        }
        switch ($this->mode) {
            case self::BOOLEAN_MODE:
                return 1;
            case self::LOGARITHMIC_MODE:
                return (1 + log($count));
            case self::AUGMENTED_MODE:
                return 0.5 + (0.5 * ($count / $this->getMaxFrequency()));
            case self::COUNT_MODE:
                return $count;
            case self::FREQUENCY_MODE:
            default:
                return $count / $this->getTotalTokens();
        }
    }

    protected function getTotalByToken($term)
    {
        $keyValues = $this->getKeyValues();
        return isset($keyValues[$term]) ? $keyValues[$term] : 0;
    }

    protected function getMaxFrequency()
    {
        $values = $this->getValues();
        return !empty($values) ? $values[0] : 0;
    }
}