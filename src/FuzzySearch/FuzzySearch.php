<?php

namespace FuzzySearch;

class FuzzySearch
{
    /**
     * Array to be searched
     *
     * @var array
     */
    private $list;

    /**
     * Key from array used to search
     *
     * @var string
     */
    private $key;

    public function __construct(array $list, string $key)
    {
        $this->list = $list;
        $this->key = $key;
    }

    /**
     * Perform a fuzzy searching on the list passed using the term searched
     *
     * @param string $term
     * @param integer $threshold
     * @return array
     */
    public function search(string $term, int $threshold = 3): array
    {
        $termSanitized = $this->sanitizeValue($term);

        $matches = [];
        foreach ($termSanitized as $term) {
            $match = $this->distance($term, $threshold);
            $matches = array_merge($matches, $match);
        }

        $matches = array_reverse(array_reverse(array_values(array_column(
            array_reverse($matches),
            null,
            $this->key
        ))));

        usort($matches, function ($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        return $matches;
    }

    /**
     * Calc the distance between the term and list items using levenshtein function
     *
     * @param string $term
     * @param integer $threshold
     * @return array
     */
    protected function distance(string $term, int $threshold): array
    {
        $matches = [];
        foreach ($this->list as $row) {
            $distance = levenshtein(strtolower($term), strtolower($row[$this->key]));

            if ($threshold >= $distance) {
                $row['distance'] = $distance;
                $matches[] = $row;
            }
        }

        return $matches;
    }

    /**
     * Sanitization of term using regex and getting similar values to number and its spelled out version
     *
     * @param string $term
     * @return array
     */
    protected function sanitizeValue(string $term): array
    {
        $term = $this->stripSpecialCharacters($term);
        $variationsToSearch[] = $term;

        $termParts = explode(' ', $term);
        $termParts = array_map(function ($word) {
            /*
             * TODO: Make this function also handle ordinal numbers (5th -> fifth)
             */
            $isNumberSpelled = $this->wordsToInt($word);

            return is_numeric($word) ? $this->intToWords($word) : ($isNumberSpelled > 0 ? $isNumberSpelled : $word);
        }, $termParts);

        $partsJoined = implode(' ', $termParts);
        if ($partsJoined !== $term) {
            $variationsToSearch[] = $partsJoined;
        }

        return $variationsToSearch;
    }

    /**
     * Remove special characters and slashs using regex
     *
     * @param string $term
     * @return string
     */
    protected function stripSpecialCharacters(string $term): string
    {
        $newTerm = preg_replace('/[^\da-z ]/i', '', $term);
        $newTerm = preg_replace("/\//", ' ', $term);

        return trim($newTerm);
    }

    /**
     * Convert a number in its spelled out version
     *
     * @param string $term
     * @return string
     */
    protected function intToWords(string $term): string
    {
        /**
         * TODO: Consider detecting 4-digit numbers and splitting them into two digit chunks
         * Scenario: We want 1789 to be read as "seventeen eighty-nine".
         */
        $f = new \NumberFormatter('en', \NumberFormatter::SPELLOUT);

        return $f->format($term);
    }

    /**
     * Convert a spelled out number in its numeric version
     *
     * @param string $term
     * @return integer
     */
    protected function wordsToInt(string $term): int
    {
        // Replace all number words with an equivalent numeric value
        $data = strtr(
            $term,
            [
                'zero' => '0',
                'one' => '1',
                'two' => '2',
                'three' => '3',
                'four' => '4',
                'five' => '5',
                'six' => '6',
                'seven' => '7',
                'eight' => '8',
                'nine' => '9',
                'ten' => '10',
                'eleven' => '11',
                'twelve' => '12',
                'thirteen' => '13',
                'fourteen' => '14',
                'fifteen' => '15',
                'sixteen' => '16',
                'seventeen' => '17',
                'eighteen' => '18',
                'nineteen' => '19',
                'twenty' => '20',
                'thirty' => '30',
                'forty' => '40',
                'fourty' => '40', // common misspelling
                'fifty' => '50',
                'sixty' => '60',
                'seventy' => '70',
                'eighty' => '80',
                'ninety' => '90',
                'hundred' => '100',
                'thousand' => '1000',
                'million' => '1000000',
                'billion' => '1000000000',
                'and' => '',
            ]
        );

        // Coerce all tokens to numbers
        $parts = array_map(
            function ($val) {
                return floatval($val);
            },
            preg_split('/[\s-]+/', $data)
        );

        $stack = new \SplStack(); // Current work stack
        $sum = 0; // Running total
        $last = null;

        foreach ($parts as $part) {
            if (!$stack->isEmpty()) {
                // We're part way through a phrase
                if ($stack->top() > $part) {
                    // Decreasing step, e.g. from hundreds to ones
                    if ($last >= 1000) {
                        // If we drop from more than 1000 then we've finished the phrase
                        $sum += $stack->pop();
                        // This is the first element of a new phrase
                        $stack->push($part);
                    } else {
                        // Drop down from less than 1000, just addition
                        // e.g. "seventy one" -> "70 1" -> "70 + 1"
                        $stack->push($stack->pop() + $part);
                    }
                } else {
                    // Increasing step, e.g ones to hundreds
                    $stack->push($stack->pop() * $part);
                }
            } else {
                // This is the first element of a new phrase
                $stack->push($part);
            }

            // Store the last processed part
            $last = $part;
        }

        return $sum + $stack->pop();
    }
}
