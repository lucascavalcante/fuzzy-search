<?php

namespace FuzzySearch;

class FuzzySearch
{
    private $list;

    public function __construct(array $list)
    {
        $this->list = $list;
    }

    public function search($term, $limit = 3)
    {
        $matched = [];

        foreach ($this->list as $row) {
            $distance = levenshtein($term, $row);

            if ($limit >= $distance) {
                $matched[] = [$distance, $row];
            }
        }

        return $this->transformResult($this->sortMatchedStrings($matched));
    }

    protected function sortMatchedStrings(array $matched)
    {
        usort($matched, function (array $left, array $right) {
            return ($left[0] - $right[0]);
        });

        return $matched;
    }

    protected function transformResult(array $matched)
    {
        $iterator = function (array $element) {
            return $element[1];
        };

        return array_map($iterator, $matched);
    }
}
