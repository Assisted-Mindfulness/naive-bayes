<?php

declare(strict_types=1);

namespace AssistedMindfulness\NaiveBayes;

use BackedEnum;
use Brick\Math\BigDecimal;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use UnitEnum;

class Classifier
{
    /**
     * The callback used to tokenize input text.
     *
     * @var ?callable(string): iterable<int, string>
     */
    protected $tokenizer;

    /**
     * The word occurrence counts keyed by category and token.
     *
     * @var array<int|string, array<string, int>>
     */
    protected array $words = [];

    /**
     * The learned document counts keyed by category.
     *
     * @var array<int|string, int>
     */
    protected array $documents = [];

    /**
     * Indicates whether learned document counts should influence the
     * prior probability assigned to each category.
     */
    protected bool $uneven = false;

    /**
     * The number of decimal places used when exact rational probabilities
     * are converted into the decimal values returned to the caller.
     *
     * @var positive-int
     */
    protected int $scale = 16;

    /**
     * Set a custom tokenizer function for tokenizing input strings.
     *
     * @param  callable(string): iterable<int, string>  $tokenizer
     */
    public function setTokenizer(callable $tokenizer): self
    {
        $this->tokenizer = $tokenizer;

        return $this;
    }

    /**
     * Get the word counts for a specific type or for every learned type.
     *
     * @return array<string, int>|array<int|string, array<string, int>>
     */
    public function getWords(UnitEnum|int|string|null $type = null): array
    {
        if ($type === null) {
            return $this->words;
        }

        $type = $this->normalizeType($type);

        return $this->words[$type] ?? [];
    }

    /**
     * Tokenize the given string into individual words.
     *
     * @param  string  $string  The input string to tokenize.
     * @return Collection<int, string> A collection of tokens.
     */
    public function tokenize(string $string): Collection
    {
        if ($this->tokenizer) {
            /** @var iterable<int, string> */
            $tokens = call_user_func($this->tokenizer, $string);

            return collect($tokens);
        }

        return Str::of($string)
            ->lower()
            ->matchAll('/[[:alpha:]]+/u')
            ->map(fn (mixed $word): string => is_string($word) ? $word : '')
            ->filter(
                fn (string $word): bool => Str::length($word) > 3
            )
            ->values();
    }

    /**
     * Learn from a statement by updating its word and document counts.
     */
    public function learn(string $statement, UnitEnum|int|string $type): self
    {
        $type = $this->normalizeType($type);

        foreach ($this->tokenize($statement) as $word) {
            $this->incrementWord($type, $word);
        }

        $this->incrementType($type);

        return $this;
    }

    /**
     * Calculate the normalized probability of each type for the given statement.
     *
     * @return Collection<int|string, BigDecimal>
     */
    public function guess(string $statement): Collection
    {
        if ($this->documents !== []) {
            return $this->calculateProbabilities($statement);
        }

        return collect();
    }

    /**
     * Calculate probabilities after at least one category has been learned.
     *
     * @return Collection<int|string, BigDecimal>
     */
    private function calculateProbabilities(string $statement): Collection
    {
        $vocabulary = collect($this->words)
            ->flatMap(fn (array $words): array => array_keys($words))
            ->unique()
            ->values();

        $words = $this->tokenize($statement)
            ->filter(fn (string $word): bool => $vocabulary->containsStrict($word));

        $vocabularySize = $vocabulary->count();

        /** @var array<int|string, BigRational> $scores */
        $scores = collect($this->documents)
            ->map(function (int $_count, int|string $type) use ($words, $vocabularySize): BigRational {
                $likelihood = $this->priorProbability($type);

                foreach ($words as $word) {
                    $likelihood = $likelihood->multipliedBy(
                        $this->conditionalProbability($word, $type, $vocabularySize)
                    );
                }

                return $likelihood;
            })
            ->all();

        $total = array_reduce(
            $scores,
            fn (BigRational $total, BigRational $score): BigRational => $total->plus($score),
            BigRational::zero()
        );

        uksort(
            $scores,
            fn (int|string $left, int|string $right): int => $scores[$left]->compareTo($scores[$right])
                ?: $left <=> $right
        );

        return $this->normalizeProbabilities($scores, $total);
    }

    /**
     * Get the most likely type for the given statement.
     */
    public function most(string $statement): int|string
    {
        $type = $this->guess($statement)->keys()->last();

        throw_unless(is_int($type) || is_string($type), LogicException::class, 'The classifier must learn at least one document before making a guess.');

        return $type;
    }

    /**
     * Account for uneven category sizes when calculating prior probabilities.
     */
    public function uneven(bool $enabled = true): self
    {
        $this->uneven = $enabled;

        return $this;
    }

    /**
     * Set the number of decimal places used for returned probabilities.
     */
    public function scale(int $scale): self
    {
        if ($scale < 1) {
            throw new InvalidArgumentException('The scale must be at least 1.');
        }

        $this->scale = $scale;

        return $this;
    }

    /**
     * Increment the document count for the given type.
     */
    protected function incrementType(int|string $type): void
    {
        $this->documents[$type] ??= 0;

        $this->documents[$type]++;
    }

    /**
     * Increment the word count for the given type.
     */
    protected function incrementWord(int|string $type, string $word): void
    {
        $this->words[$type][$word] ??= 0;

        $this->words[$type][$word]++;
    }

    /**
     * Calculate the Laplace-smoothed probability of a word occurring in a type.
     *
     * @param  string  $word  The word to calculate probability for.
     * @param  int|string  $type  The type to calculate probability in.
     * @param  int<0, max>  $vocabularySize  The number of unique learned words.
     */
    protected function conditionalProbability(string $word, int|string $type, int $vocabularySize): BigRational
    {
        $count = $this->words[$type][$word] ?? 0;

        return BigRational::ofFraction(
            $count + 1,
            array_sum($this->words[$type]) + $vocabularySize
        );
    }

    /**
     * Calculate the prior probability of a type.
     *
     * @param  int|string  $type  The type to calculate probability for.
     */
    protected function priorProbability(int|string $type): BigRational
    {
        if (! $this->uneven) {
            return BigRational::ofFraction(1, count($this->documents));
        }

        return BigRational::ofFraction(
            $this->documents[$type] + 1,
            array_sum($this->documents) + count($this->documents)
        );
    }

    /**
     * Convert exact scores into decimals that add up to one at the configured scale.
     *
     * @param  array<int|string, BigRational>  $scores
     * @return Collection<int|string, BigDecimal>
     */
    protected function normalizeProbabilities(array $scores, BigRational $total): Collection
    {
        $probabilities = collect($scores)
            ->map(
                fn (BigRational $score): BigDecimal => $score
                    ->dividedBy($total)
                    ->toScale($this->scale, RoundingMode::Down)
            );

        $roundedTotal = $probabilities->reduce(
            fn (BigDecimal $sum, BigDecimal $probability): BigDecimal => $sum->plus($probability),
            BigDecimal::zero()->toScale($this->scale)
        );

        $remainingUnits = BigDecimal::one()
            ->toScale($this->scale)
            ->minus($roundedTotal)
            ->getUnscaledValue()
            ->toInt();

        $unit = BigDecimal::ofUnscaledValue(1, $this->scale);
        $typesToRoundUp = $probabilities->keys()
            ->reverse()
            ->take($remainingUnits);

        return $probabilities->map(
            fn (BigDecimal $probability, int|string $type): BigDecimal => $typesToRoundUp->containsStrict($type)
                ? $probability->plus($unit)
                : $probability
        );
    }

    /**
     * Normalize scalar and enum category identifiers into array keys.
     */
    protected function normalizeType(UnitEnum|int|string $type): int|string
    {
        return match (true) {
            $type instanceof BackedEnum => $type->value,
            $type instanceof UnitEnum => $type->name,
            default => $type,
        };
    }
}
