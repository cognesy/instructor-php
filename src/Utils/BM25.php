<?php
namespace Cognesy\Instructor\Utils;

class BM25
{
    private float $k;
    private float $b;
    private array $idfDictionary = [];
    private array $tfDictionary = [];
    private float $avgDocLength;
    private array $stopwords;

    private array $englishStopwords = [
        "a", "about", "above", "after", "again", "against", "all", "am", "an", "and", "any", "are", "aren't", "as", "at",
        "be", "because", "been", "before", "being", "below", "between", "both", "but", "by",
        "can't", "cannot", "could", "couldn't",
        "did", "didn't", "do", "does", "doesn't", "doing", "don't", "down", "during",
        "each",
        "few", "for", "from", "further",
        "had", "hadn't", "has", "hasn't", "have", "haven't", "having", "he", "he'd", "he'll", "he's", "her", "here", "here's", "hers", "herself", "him", "himself", "his", "how", "how's",
        "i", "i'd", "i'll", "i'm", "i've", "if", "in", "into", "is", "isn't", "it", "it's", "its", "itself",
        "let's",
        "me", "more", "most", "mustn't", "my", "myself",
        "no", "nor", "not",
        "of", "off", "on", "once", "only", "or", "other", "ought", "our", "ours", "ourselves", "out", "over", "own",
        "same", "shan't", "she", "she'd", "she'll", "she's", "should", "shouldn't", "so", "some", "such",
        "than", "that", "that's", "the", "their", "theirs", "them", "themselves", "then", "there", "there's", "these", "they", "they'd", "they'll", "they're", "they've", "this", "those", "through", "to", "too",
        "under", "until", "up",
        "very",
        "was", "wasn't", "we", "we'd", "we'll", "we're", "we've", "were", "weren't", "what", "what's", "when", "when's", "where", "where's", "which", "while", "who", "who's", "whom", "why", "why's", "with", "won't", "would", "wouldn't",
        "you", "you'd", "you'll", "you're", "you've", "your", "yours", "yourself", "yourselves"
    ];

    /**
     * BM25 constructor.
     *
     * @param float $k Constant used to limit the growth of TF. Default is 1.2.
     * @param float $b Constant that determines how much impact document length has on the score. Default is 0.75.
     * @param array $stopwords Array of stopwords to ignore in queries. Default is an empty array.
     */
    public function __construct(float $k = 1.2, float $b = 0.75, array $stopwords = []) {
        $this->k = $k;
        $this->b = $b;
        if (empty($stopwords)) {
            $stopwords = $this->englishStopwords;
        }
        $this->stopwords = array_flip(array_map('mb_strtolower', $stopwords));
    }

    /**
     * Preprocess documents and build TF-IDF dictionaries
     *
     * @param array $docs Array of documents
     */
    public function preprocess(array $docs): void {
        $N = count($docs);
        $totalLength = 0;
        $termFrequencies = [];

        foreach ($docs as $docId => $doc) {
            $words = $this->tokenize($doc);
            $docLength = count($words);
            $totalLength += $docLength;

            foreach ($words as $word) {
                $this->tfDictionary[$docId][$word] = ($this->tfDictionary[$docId][$word] ?? 0) + 1;
                $termFrequencies[$word][$docId] = true;
            }
        }

        $this->avgDocLength = $totalLength / $N;

        foreach ($termFrequencies as $term => $termDocs) {
            $this->idfDictionary[$term] = log(($N - count($termDocs) + 0.5) / (count($termDocs) + 0.5) + 1);
        }
    }

    /**
     * Tokenize a document or query into words
     *
     * @param string $text
     * @return array
     */
    private function tokenize(string $text): array {
        // Simple tokenization by splitting on whitespace and converting to lowercase
        $words = preg_split('/\s+/', mb_strtolower($text));

        // Remove stopwords
        return array_values(array_diff($words, array_keys($this->stopwords)));
    }

    /**
     * Convert a string query into an array of keywords
     *
     * @param string $query
     * @return array
     */
    public function processQuery(string $query): array {
        $keywords = $this->tokenize($query);

        // Remove duplicates and reindex array
        return array_values(array_unique($keywords));
    }

    /**
     * Calculate relevance score
     *
     * @param float $idf Inverse document frequency of the keyword
     * @param float $tf Term frequency of the keyword in the document
     * @param float $docLength Length of the current document
     * @return float
     */
    public function score(float $idf, float $tf, float $docLength): float {
        $L = $docLength / $this->avgDocLength;
        return ($idf * ($this->k + 1) * $tf) / ($this->k * (1.0 - $this->b + $this->b * $L) + $tf);
    }

    /**
     * Calculate relevance of a document for given keywords
     *
     * @param array $keywords Array of keywords
     * @param int $docId Document ID
     * @return float
     */
    public function documentScore(array $keywords, int $docId): float {
        $score = 0;
        $docLength = array_sum($this->tfDictionary[$docId]);

        foreach ($keywords as $keyword) {
            $idf = $this->idfDictionary[$keyword] ?? 0;
            $tf = $this->tfDictionary[$docId][$keyword] ?? 0;
            $score += $this->score($idf, $tf, $docLength);
        }

        return $score;
    }

    /**
     * Search for documents relevant to given keywords or query string
     *
     * @param array|string $query Array of keywords or a string query
     * @return array Sorted array of document IDs and their relevance scores
     */
    public function search($query): array {
        $keywords = is_array($query) ? $query : $this->processQuery($query);

        $scores = [];

        foreach ($this->tfDictionary as $docId => $terms) {
            $scores[$docId] = $this->documentScore($keywords, $docId);
        }

        arsort($scores);
        return $scores;
    }

    /**
     * Set stopwords
     *
     * @param array $stopwords Array of stopwords to ignore in queries
     * @return self
     */
    public function setStopwords(array $stopwords): self {
        $this->stopwords = array_flip(array_map('mb_strtolower', $stopwords));
        return $this;
    }

    /**
     * Set the k parameter
     * The k parameter controls the impact of term frequency saturation. It determines how much the score
     * should increase when a term appears multiple times in a document.
     *
     * @param float $k
     * @return self
     */
    public function setK(float $k): self {
        $this->k = $k;
        return $this;
    }

    /**
     * Set the b parameter
     * The b parameter controls the scaling by document length. It determines how much to penalize or
     * favor documents based on their length compared to the average document length.
     *
     * @param float $b
     * @return self
     */
    public function setB(float $b): self {
        $this->b = $b;
        return $this;
    }
}
