<?php

namespace AssistedMindfulness\NaiveBayes;

use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Classifier
{
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
     * @return Collection<string, string>
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

    public function most(string $statement): string
    {
        /** @var string */
        return $this->guess($statement)->keys()->first();
    }

    /**
     * @return $this
     */
    public function learn(string $statement, string $type): self
    {
        foreach ($this->getWords($statement) as $word) {
            $this->incrementWord($type, $word);
        }

        $this->incrementType($type);

        return $this;
    }

    /**
     * @return self
     */
    public function uneven(bool $enabled = true): self
    {
        $this->uneven = $enabled;

        return $this;
    }

    /**
     * Increment the document count for the type
     */
    public function incrementType(string $type): void
    {
        if (! isset($this->documents[$type])) {
            $this->documents[$type] = 0;
        }

        $this->documents[$type]++;
    }

    /**
     * Increment the word count for the given type
     */
    public function incrementWord(string $type, string $word): void
    {
        if (! isset($this->words[$type][$word])) {
            $this->words[$type][$word] = 0;
        }

        $this->words[$type][$word]++;
    }

    /**
     * @return float|int
     */
    public function p(string $word, string $type)
    {
        $count = $this->words[$type][$word] ?? 0;

        return ($count + 1) / (array_sum($this->words[$type]) + 1);
    }

    /**
     * @return float|int
     */
    public function pTotal(string $type)
    {
        return $this->uneven
            ? ($this->documents[$type] + 1) / (array_sum($this->documents) + 1)
            : 1;
    }

    /**
     * @return Collection<int, string>
     */
    public function getWords(string $string): Collection
    {
        return Str::of($string)
            ->lower()
            ->matchAll('/[[:alpha:]]+/u');
    }
}
