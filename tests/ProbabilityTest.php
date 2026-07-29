<?php

declare(strict_types=1);

namespace AssistedMindfulness\NaiveBayes\Tests;

use AssistedMindfulness\NaiveBayes\Classifier;
use Brick\Math\BigDecimal;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProbabilityTest extends TestCase
{
    #[Test]
    public function returnsNormalizedProbabilities(): void
    {
        $scores = (new Classifier)
            ->scale(8)
            ->learn('Bright sunny morning', 'positive')
            ->learn('Dark rainy evening', 'negative')
            ->guess('Bright morning');

        $this->assertSame(['negative', 'positive'], $scores->keys()->all());
        $this->assertSame('0.20000000', (string) $scores->get('negative'));
        $this->assertSame('0.80000000', (string) $scores->get('positive'));
        $this->assertSame(
            '1.00000000',
            (string) $scores->reduce(
                fn (BigDecimal $total, BigDecimal $score): BigDecimal => $total->plus($score),
                BigDecimal::zero()
            )
        );
    }

    #[Test]
    public function preservesNormalizedTotalAtLowScale(): void
    {
        $scores = (new Classifier)
            ->scale(1)
            ->learn('Shared vocabulary', 'alpha')
            ->learn('Shared vocabulary', 'beta')
            ->learn('Shared vocabulary', 'gamma')
            ->guess('Shared');

        $this->assertSame('0.3', (string) $scores->get('alpha'));
        $this->assertSame('0.3', (string) $scores->get('beta'));
        $this->assertSame('0.4', (string) $scores->get('gamma'));
        $this->assertSame(
            '1.0',
            (string) $scores->reduce(
                fn (BigDecimal $total, BigDecimal $score): BigDecimal => $total->plus($score),
                BigDecimal::zero()
            )
        );
    }

    #[Test]
    public function configuresProbabilityScaleFluently(): void
    {
        $classifier = new Classifier;

        $this->assertSame($classifier, $classifier->scale(32));

        $scores = $classifier
            ->learn('Bright sunny morning', 'positive')
            ->learn('Dark rainy evening', 'negative')
            ->guess('Bright morning');

        $this->assertSame(
            '0.80000000000000000000000000000000',
            (string) $scores->get('positive')
        );
    }

    #[Test]
    #[DataProvider('invalidScales')]
    public function rejectsInvalidProbabilityScale(int $scale): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least 1');

        (new Classifier)->scale($scale);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidScales(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'minimum integer' => [PHP_INT_MIN];
    }

    #[Test]
    public function returnsNoProbabilitiesBeforeTraining(): void
    {
        $this->assertTrue((new Classifier)->guess('Anything')->isEmpty());
    }

    #[Test]
    public function requiresTrainingBeforeReturningMostLikelyType(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must learn at least one document');

        (new Classifier)->most('Anything');
    }

    #[Test]
    public function appliesLaplaceSmoothingToUnseenCategoryWords(): void
    {
        $scores = (new Classifier)
            ->learn('Bright sunny morning', 'positive')
            ->learn('Dark rainy evening', 'negative')
            ->guess('Bright');

        $negative = $scores->get('negative');
        $positive = $scores->get('positive');

        $this->assertInstanceOf(BigDecimal::class, $negative);
        $this->assertInstanceOf(BigDecimal::class, $positive);
        $this->assertTrue($negative->isPositive());
        $this->assertTrue($positive->isGreaterThan($negative));
    }

    #[Test]
    public function repeatedEvidenceStrengthensItsCategoryProbability(): void
    {
        $classifier = (new Classifier)
            ->learn('Bright sunny morning', 'positive')
            ->learn('Dark rainy evening', 'negative');

        $single = $classifier->guess('Bright')->get('positive');
        $repeated = $classifier->guess('Bright bright')->get('positive');

        $this->assertInstanceOf(BigDecimal::class, $single);
        $this->assertInstanceOf(BigDecimal::class, $repeated);
        $this->assertTrue($repeated->isGreaterThan($single));
    }

    #[Test]
    public function treatsEmptyAndUnknownInputAsTheSameEvidence(): void
    {
        $classifier = (new Classifier)
            ->learn('Bright sunny morning', 'positive')
            ->learn('Dark rainy evening', 'negative');

        $empty = $classifier->guess('');
        $unknown = $classifier->guess('Completely unfamiliar vocabulary');

        $this->assertEquals($empty, $unknown);
        $this->assertSame('0.5000000000000000', (string) $empty->get('negative'));
        $this->assertSame('0.5000000000000000', (string) $empty->get('positive'));
    }

    #[Test]
    public function usesSmoothedDocumentPriorsForUnevenTrainingData(): void
    {
        $scores = (new Classifier)
            ->learn('First positive document', 'positive')
            ->learn('Second positive document', 'positive')
            ->learn('Only negative document', 'negative')
            ->uneven()
            ->guess('');

        $this->assertSame('0.4000000000000000', (string) $scores->get('negative'));
        $this->assertSame('0.6000000000000000', (string) $scores->get('positive'));
    }

    #[Test]
    public function canDisableUnevenDocumentPriorsAgain(): void
    {
        $classifier = (new Classifier)
            ->learn('First positive document', 'positive')
            ->learn('Second positive document', 'positive')
            ->learn('Only negative document', 'negative');

        $balanced = $classifier->guess('');
        $uneven = $classifier->uneven()->guess('');
        $balancedAgain = $classifier->uneven(false)->guess('');

        $this->assertSame('0.5000000000000000', (string) $balanced->get('positive'));
        $this->assertSame('0.6000000000000000', (string) $uneven->get('positive'));
        $this->assertEquals($balanced, $balancedAgain);
    }

    #[Test]
    public function handlesCategoriesLearnedFromEmptyStatements(): void
    {
        $classifier = (new Classifier)
            ->learn('', 'alpha')
            ->learn('A 12 !', 'beta');

        $this->assertSame([], $classifier->getWords('alpha'));
        $this->assertSame([], $classifier->getWords('beta'));
        $this->assertSame('0.5000000000000000', (string) $classifier->guess('Unknown')->get('alpha'));
        $this->assertSame('0.5000000000000000', (string) $classifier->guess('Unknown')->get('beta'));
    }

    #[Test]
    public function returnsProbabilityOneForASingleCategory(): void
    {
        $classifier = (new Classifier)
            ->learn('Only possible category', 'single');

        $this->assertSame('1.0000000000000000', (string) $classifier->guess('Anything')->get('single'));
        $this->assertSame('single', $classifier->most('Anything'));
    }

    #[Test]
    public function guessingDoesNotMutateLearnedWordCounts(): void
    {
        $classifier = (new Classifier)
            ->learn('Bright bright morning', 'positive')
            ->learn('Dark rainy evening', 'negative');

        $before = $classifier->getWords();

        $classifier->guess('Bright unknown bright');

        $this->assertSame($before, $classifier->getWords());
    }

    #[Test]
    public function keepsTiedRankingsStableRegardlessOfTrainingOrder(): void
    {
        $first = (new Classifier)
            ->learn('Shared vocabulary', 'alpha')
            ->learn('Shared vocabulary', 'beta')
            ->guess('Shared');

        $second = (new Classifier)
            ->learn('Shared vocabulary', 'beta')
            ->learn('Shared vocabulary', 'alpha')
            ->guess('Shared');

        $this->assertSame(['alpha', 'beta'], $first->keys()->all());
        $this->assertSame($first->keys()->all(), $second->keys()->all());
        $this->assertEquals($first, $second);
    }

    #[Test]
    public function keepsRankingStableAcrossProbabilityScales(): void
    {
        $train = static fn (Classifier $classifier): Classifier => $classifier
            ->learn('Bright sunny morning', 'positive')
            ->learn('Dark rainy evening', 'negative');

        $lowPrecision = $train((new Classifier)->scale(1))->guess('Bright morning');
        $highPrecision = $train((new Classifier)->scale(32))->guess('Bright morning');

        $this->assertSame($lowPrecision->keys()->all(), $highPrecision->keys()->all());
        $this->assertSame('positive', $lowPrecision->keys()->last());
        $this->assertSame('positive', $highPrecision->keys()->last());
    }

    /**
     * @param  positive-int  $scale
     * @param  array<int|string, string>  $training
     */
    #[Test]
    #[DataProvider('probabilityMatrices')]
    public function maintainsProbabilityInvariantsAcrossDatasets(
        int $scale,
        array $training,
        string $statement
    ): void {
        $classifier = (new Classifier)->scale($scale);

        foreach ($training as $type => $document) {
            $classifier->learn($document, $type);
        }

        $scores = $classifier->guess($statement);
        $total = BigDecimal::zero()->toScale($scale);
        $previous = null;

        foreach ($scores as $score) {
            $this->assertTrue($score->isPositiveOrZero());
            $this->assertTrue($score->isLessThanOrEqualTo(BigDecimal::one()));

            if ($previous instanceof BigDecimal) {
                $this->assertTrue($previous->isLessThanOrEqualTo($score));
            }

            $total = $total->plus($score);
            $previous = $score;
        }

        $this->assertTrue($total->isEqualTo(BigDecimal::one()));
        $this->assertSame($scores->keys()->last(), $classifier->most($statement));
    }

    /**
     * @return iterable<string, array{positive-int, array<int|string, string>, string}>
     */
    public static function probabilityMatrices(): iterable
    {
        yield 'binary at minimum scale' => [
            1,
            [
                'positive' => 'Bright sunny morning',
                'negative' => 'Dark rainy evening',
            ],
            'Bright morning',
        ];

        yield 'three-way tie' => [
            2,
            [
                'alpha' => 'Shared vocabulary',
                'beta' => 'Shared vocabulary',
                'gamma' => 'Shared vocabulary',
            ],
            'Shared',
        ];

        yield 'mixed scalar category identifiers' => [
            8,
            [
                10 => 'Laravel framework package',
                'neutral' => 'Unrelated generic document',
                20 => 'Testing framework package',
            ],
            'Laravel package',
        ];

        yield 'unknown evidence' => [
            16,
            [
                'first' => 'Bright sunny morning',
                'second' => 'Dark rainy evening',
                'third' => 'Quiet snowy afternoon',
            ],
            'Completely unknown vocabulary',
        ];

        yield 'single category at high scale' => [
            32,
            [
                'only' => 'Only possible category',
            ],
            'Anything',
        ];
    }
}
