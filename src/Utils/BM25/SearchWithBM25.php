<?php
namespace Cognesy\Instructor\Utils\BM25;

// Source: https://gist.github.com/jtejido/

use JetBrains\PhpStorm\Deprecated;
use NlpTools\Tokenizers\WhitespaceTokenizer;
use NlpTools\Documents\TrainingSet;
use NlpTools\Documents\TokensDocument;
use NlpTools\Utils\Normalizers\Normalizer;

/*
 * This is a thin wrapper implementation for the BM25 Class found here:
 * https://gist.github.com/jtejido/6e07a6b6670786c79877780f9532ade5
 *
 *
 * This takes an array of untokenized documents and its identifier (for scoring's identification use)
 *
 * Example usage:
 *
 * $docs = array('<id or title>' => <path_to_document1>, '<id or title>' => <path_to_document2>, ...);
 * $search = new SearchWithBM25($docs);
 * $search->setParams([
 *             'mode' => 'bm25',
 *             'k' => '1.6',
 *             'b' => '0.75'
 *             ]);
 * $search->search('big fish');
 */

#[Deprecated('Not used - may be removed in the future.')]
class SearchWithBM25
{
    const B = 0.75;

    const K = 1.3;

    const type = array('bm25', 'bm25p');
    private array $documents;
    private float $k;
    private float $b;
    private TrainingSet $tset;

    public function __construct(array $documents)
    {
        $this->documents = $documents;
        $this->b = self::B;
        $this->k = self::K;
        $this->type = 'bm25';
        $tok = new WhitespaceTokenizer();
        $tset = new TrainingSet();
        $english = Normalizer::factory();
        foreach($this->documents as $class => $doc){
            $tset->addDocument(
                $class,
                new TokensDocument(
                    $english->normalizeAll($tok->tokenize($doc))
                )
            );
        }
        $this->tset = $tset;
        
    }

    /**
     * Returns BM25 mode.
     *
     * @param  array $mode
     * @return self
     */
    public function setParams(array $mode)
    {

        if(!in_array(strtolower($mode['mode']),self::type)){
            throw new \InvalidArgumentException(
                 "The type is not accepted."
            );
        }

        $this->type = isset($mode['mode']) ? $mode['mode'] : 'bm25';
        $this->b = isset($mode['k']) ? $mode['k'] : self::B;
        $this->k = isset($mode['b']) ? $mode['b'] : self::K;
        return $this;
    }

    /**
     * Returns search ordered by rank.
     *
     * @param  string $term
     * @return array
     */

    public function search($term)
    {
        $score = new BM25($this->tset, $this->type, $this->b, $this->k);
        $tok = new WhitespaceTokenizer();
        $english = Normalizer::factory();
        return $score->getScores($english->normalizeAll($tok->tokenize($term)));
    }
}
