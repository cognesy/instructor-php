<?php
namespace Cognesy\Instructor\Utils\BM25;

// Source: https://gist.github.com/jtejido/

use JetBrains\PhpStorm\Deprecated;
use NlpTools\Documents\TrainingSet;

/**
 * BM25 is a class for ranking documents against a query.
 *
 * The implementation is based on the paper by Stephen E. Robertson, Steve Walker, Susan Jones, 
 * Micheline Hancock-Beaulieu & Mike Gatford (November 1994).
 * that can be found at http://trec.nist.gov/pubs/trec3/t3_proceedings.html.
 *
 * Some modifications have been made to allow for non-negative scoring as suggested here.
 * https://doc.rero.ch/record/16754/files/Dolamic_Ljiljana_-_When_Stopword_Lists_Make_the_Difference_20091218.pdf
 *
 * We also made use of a delta(δ) value of 1, which modifies BM25 to account for an issue against penalizing 
 * long documents and allowing shorter ones to dominate. The delta values assures BM25 to be lower-bounded.
 * http://sifaka.cs.uiuc.edu/~ylv2/pub/cikm11-lowerbound.pdf
 *
 * An addition for extending current implementation of FreqDist is needed (which has additional options for SMART tf-idf 
 * notations).
 *
 *
 * Example usage:
 * $english = Normalizer::factory();
 * $tset = new TrainingSet();
 * $tset->addDocument(
 *  "id1",
 *  new TokensDocument(
 *      $english->normalizeAll(array('a','whale','is','a','fish'))
 *  )
 * );
 *
 * $tset->addDocument(
 *  "id2",
 *  new TokensDocument(
 *      $english->normalizeAll(array('a','Whale','is','a','mammal'))
 *  )
 * );
 * $tset->addDocument(
 *  "id3",
 *  new TokensDocument(
 *      $english->normalizeAll(array('a','man','is','a','mammal'))
 *  )
 * );
 *
 * $bm25 = new BM25($tset);
 *
 * $scores = $bm25->getScores('whale mammal');
 *
 *
 * @author Jericko Tejido <jtbibliomania@gmail.com>
 */

#[Deprecated('Not used - may be removed in the future.')]
class BM25
{

    const B = 0.75;

    const K = 1.3;

    const D = 1;

    const COUNT_MODE = 5;

    const type = array('bm25', 'bm25p');

    protected $tset;

    protected $b;

    protected $k;

    protected $d;

    protected $type;

    public function __construct(TrainingSet $tset, $type = null, $b = self::B, $k = self::K, $d = self::D)
    {
        $this->tset = $tset;
        $this->b = $b;
        $this->k = $k;
        $this->type = $type;


        if(strtolower($this->type) === null) {
            $this->type = 'bm25';
            $this->d = 0;
        } elseif(strtolower($this->type) === 'bm25') {
            $this->d = 0;
        } elseif(strtolower($this->type) === 'bm25p') {
            $this->d = $d;
        }
        elseif(!in_array(strtolower($this->type),self::type)){
            throw new \InvalidArgumentException(
                 "The type is not accepted."
            );
        }

    }


    /**
     * Returns Idf(Inverse Document Frequency).
     * log{1+[(n−dfj +0.5)/(dfj +0.5)]}
     * To avoid negative results when the underlying term tj occurs in more than half of
     * the documents (dfj > n/2) we add 1 before getting log().
     *
     * @param  int $nqi
     * @return float
     */
    private function getIdf($nqi)
    {
        return log(1 + ((count($this->tset)-$nqi+0.5)/($nqi + 0.5)));
    }

    /**
     * Returns Tf(Term Frequency).
     *
     * @param  array $document
     * @param  string $term
     * @return float
     */
    private function getTf($document, $term)
    {
        $freqDist = new TermFrequency($document, self::COUNT_MODE);
        return $freqDist->getTf($term);
    }

    /**
     * Returns Okapi BM25's Scoring value per added document.
     * BM25 = idf*( ($tf * (k + 1))/($tf + k * (1-b+b*(D/Av_D))) )
     * BM25+ = idf*( ($tf * (k + 1))/($tf + k * (1-b+b*(D/Av_D))) + δ)
     *
     * @param  string $term
     * @param  float $idf
     * @param  int $allLength
     * @param  array $score
     * @return array
     */
    private function getBm25($tf, $idf, $length, $avg_dl)
    {

        $num = $tf * ($this->k + 1);
        $denom = $tf + $this->k * (1 - $this->b + $this->b * ($length / $avg_dl));
        return $idf * (($num / $denom) + $this->d);
    }

    /**
     * Returns Score ranking per Documents added by ascending order.
     *
     * @param  string $term
     * @return array
     */
    public function getScores(array $terms)
    {

        $allLength = $this->getAllLength();
        $score = array();

        foreach($terms as $term) {
            $nqi = $this->getNQi($term);
            $idf = $this->getIdf($nqi);
            foreach ($this->tset as $class => $doc) {
                $score[$class] = isset($score[$class]) ? $score[$class] : 0;
                $tf = $this->getTf($doc->getDocumentData(), $term);
                $length = count($doc->getDocumentData());
                $avg_dl = $length/$allLength;
                $score[$class] += $this->getBm25($tf, $idf, $length, $avg_dl);
            }
        }

        arsort($score);
        return $score;

    }

    /**
     * Returns number of documents containing the query word.
     *
     * @param  string $term
     * @return int
     */
    private function getNQi($term)
    {

        $nqi = 0;

        foreach ($this->tset as $class=>$doc) {
            if(in_array($term, $doc->getDocumentData())){
                $nqi++;
            }
        }

        return $nqi;
    }

    /**
     * Returns number of words on all added documents.
     * @return int
     */
    private function getAllLength()
    {

        $allLength = 0;

        foreach ($this->tset as $class=>$doc) {
            $allLength += count($doc->getDocumentData());
        }

        return $allLength;
    }


}