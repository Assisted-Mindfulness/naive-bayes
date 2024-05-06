<?php

namespace AssistedMindfulness\NaiveBayes;

use Brick\Math\BigDecimal;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Classifier
{
    /**
     * @var ?callable(string): array<int, string>
     */
    private $tokenizer;

    /**
     * @var array<string, array<string, int>>
     */
    private array $words = [];

    /**
     * @var array<string, int>
     */
    private array $documents = [];

    private bool $uneven = false;

    /**
     * Sets a custom tokenizer function for tokenizing input strings.
     *
     * @param callable(string): array<int, string> $tokenizer
     */
    public function setTokenizer(callable $tokenizer): void
    {
        $this->tokenizer = $tokenizer;
    }

    /**
     * Retrieves the word counts associated with a specific type or all types if no key is provided.
     */
    public function getWords(int|null|string $type = null):array
    {
        return Arr::get($this->words, $type);
    }

    /**
     * Tokenizes a given string into individual words.
     *
     * @param string $string The input string to tokenize.
     * @return Collection<int, string> A collection of tokens.
     */
    public function tokenize(string $string): Collection
    {
        if ($this->tokenizer) {
            /** @var array<int, string> */
            $tokens = call_user_func($this->tokenizer, $string);

            return collect($tokens);
        }

        return Str::of($string)
            ->lower()
            ->matchAll('/[[:alpha:]]+/u');
    }

    /**
     * Learns from a given statement by updating word and document counts.
     */
    public function learn(string $statement, string $type): self
    {
        foreach ($this->tokenize($statement) as $word) {
            $this->incrementWord($type, $word);
        }

        $this->incrementType($type);

        return $this;
    }

    /**
     * Guesses the type of a given statement using Naive Bayes classification.
     */
    public function guess(string $statement): Collection
    {
        $words = $this->tokenize($statement);

        return collect($this->documents)
            ->map(function ($count, string $type) use ($words) {
                $likelihood = $this->pTotal($type);

                foreach ($words as $word) {
                    $likelihood *= $this->p($word, $type);
                }

                return (string) BigDecimal::of($likelihood);
            })
            ->sort(function ($a, $b) {
                return BigDecimal::of($a)->compareTo($b);
            });
    }

    /**
     * Retrieves the most likely type for a given statement.
     */
    public function most(string $statement): string
    {
        /** @var string */
        return $this->guess($statement)->keys()->last();
    }

    /**
     * Toggles the "uneven" mode which adjusts probability calculation for document types.
     */
    public function uneven(bool $enabled = true): self
    {
        $this->uneven = $enabled;

        return $this;
    }

    /**
     * Increment the document count for the type
     */
    private function incrementType(string $type): void
    {
        if (!isset($this->documents[$type])) {
            $this->documents[$type] = 0;
        }

        $this->documents[$type]++;
    }

    /**
     * Increment the word count for the given type
     */
    private function incrementWord(string $type, string $word): void
    {
        if (!isset($this->words[$type][$word])) {
            $this->words[$type][$word] = 0;
        }

        $this->words[$type][$word]++;
    }

    /**
     * Calculates the conditional probability of a word occurring in a type.
     *
     * @param string $word The word to calculate probability for.
     * @param string $type The type to calculate probability in.
     *
     * @return float|int The calculated probability.
     */
    private function p(string $word, string $type)
    {
        $count = $this->words[$type][$word] ?? 0;

        return ($count + 1) / (array_sum($this->words[$type]) + 1);
    }

    /**
     * Calculates the prior probability of a type.
     *
     * @param string $type The type to calculate probability for.
     *
     * @return float|int The calculated probability.
     */
    private function pTotal(string $type)
    {
        return $this->uneven
            ? ($this->documents[$type] + 1) / (array_sum($this->documents) + 1)
            : 1;
    }
}
