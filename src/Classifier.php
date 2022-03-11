<?php

namespace AssistedMindfulness\NaiveBayes;

use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Classifier
{
    /**
     * @var array
     */
    private array $words = [];

    /**
     * @var array
     */
    private array $documents = [];

    /**
     * @var bool
     */
    protected bool $uneven = false;

    /**
     * @param string $statement
     *
     * @return Collection
     */
    public function guess(string $statement): Collection
    {
        $words = $this->getWords($statement);

        return collect($this->documents)
            ->map(function ($count, string $type) use ($words) {
                $likelihood = $this->pTotal($type);

                foreach ($words as $word) {
                    $likelihood *= $this->p($word, $type);
                }

                return (string) BigDecimal::of($likelihood);
            })
            ->sortDesc();
    }

    /**
     * @param string $statement
     *
     * @return string
     */
    public function most(string $statement): string
    {
        return $this->guess($statement)->keys()->first();
    }

    /**
     * @param string $statement
     * @param string $type
     *
     * @return $this
     */
    public function learn(string $statement, string $type): Classifier
    {
        $this
            ->getWords($statement)
            ->each(function (string $word) use ($type) {
                $this->incrementWord($type, $word);
            });

        $this->incrementType($type);

        return $this;
    }

    /**
     * @param bool $enabled
     *
     * @return Classifier
     */
    public function uneven(bool $enabled = true): Classifier
    {
        $this->uneven = $enabled;

        return $this;
    }

    /**
     * Increment the document count for the type
     *
     * @param string $type
     *
     * @return void
     */
    public function incrementType(string $type): void
    {
        if (! isset($this->documents[$type])) {
            $this->documents[$type] = 0;
        }

        $this->documents[$type]++;
    }

    /**
     * Increment the word count for the type
     *
     * @param string $type
     * @param string $word
     *
     * @return void
     */
    public function incrementWord(string $type, string $word): void
    {
        if (! isset($this->words[$type][$word])) {
            $this->words[$type][$word] = 0;
        }

        $this->words[$type][$word]++;
    }

    /**
     * @param string $word
     * @param string $type
     *
     * @return float|int
     */
    public function p(string $word, string $type)
    {
        $count = $this->words[$type][$word] ?? 0;

        return ($count + 1) / (array_sum($this->words[$type]) + 1);
    }

    /**
     * @param string $type
     *
     * @return float|int
     */
    public function pTotal(string $type)
    {
        return $this->uneven
            ? ($this->documents[$type] + 1) / (array_sum($this->documents) + 1)
            : 1;
    }

    /**
     * @param string $string
     *
     * @return Collection
     */
    public function getWords(string $string): Collection
    {
        return Str::of($string)
            ->lower()
            ->matchAll('/[[:alpha:]]+/u');
    }
}
