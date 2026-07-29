<?php

declare(strict_types=1);

namespace AssistedMindfulness\NaiveBayes\Tests;

use AssistedMindfulness\NaiveBayes\Classifier;
use Brick\Math\BigRational;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExtensibilityTest extends TestCase
{
    #[Test]
    public function allowsSubclassesToCustomizeConditionalProbabilities(): void
    {
        $scores = (new BiasedClassifier)
            ->learn('Shared vocabulary', 'preferred')
            ->learn('Shared vocabulary', 'other')
            ->guess('Shared');

        $this->assertSame('0.1000000000000000', (string) $scores->get('other'));
        $this->assertSame('0.9000000000000000', (string) $scores->get('preferred'));
    }
}

final class BiasedClassifier extends Classifier
{
    #[Override]
    protected function conditionalProbability(string $word, int|string $type, int $vocabularySize): BigRational
    {
        return BigRational::ofFraction(
            $type === 'preferred' ? 9 : 1,
            10
        );
    }
}
