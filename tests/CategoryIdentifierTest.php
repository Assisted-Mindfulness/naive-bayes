<?php

declare(strict_types=1);

namespace AssistedMindfulness\NaiveBayes\Tests;

use AssistedMindfulness\NaiveBayes\Classifier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

enum SentimentCategory: int
{
    case Negative = 10;
    case Positive = 20;
}

enum TopicCategory
{
    case Personal;
    case Technical;
}

final class CategoryIdentifierTest extends TestCase
{
    #[Test]
    public function supportsStringCategoryIdentifiers(): void
    {
        $classifier = (new Classifier)
            ->learn('Bright sunny morning', 'positive')
            ->learn('Dark rainy evening', 'negative');

        $this->assertSame(
            [
                'bright' => 1,
                'sunny' => 1,
                'morning' => 1,
            ],
            $classifier->getWords('positive')
        );
        $this->assertSame('positive', $classifier->most('Bright morning'));
        $this->assertSame(
            ['negative', 'positive'],
            $classifier->guess('Bright morning')->keys()->all()
        );
    }

    #[Test]
    public function supportsIntegerCategoryIdentifiers(): void
    {
        $classifier = (new Classifier)
            ->learn('Bright sunny morning', 20)
            ->learn('Dark rainy evening', 10);

        $this->assertSame(
            [
                'bright' => 1,
                'sunny' => 1,
                'morning' => 1,
            ],
            $classifier->getWords(20)
        );
        $this->assertSame(20, $classifier->most('Bright morning'));
        $this->assertSame([10, 20], $classifier->guess('Bright morning')->keys()->all());
    }

    #[Test]
    public function supportsZeroAndEmptyStringCategoryIdentifiers(): void
    {
        $numeric = (new Classifier)->learn('Numeric category', 0);
        $string = (new Classifier)->learn('String category', '');

        $this->assertSame(0, $numeric->most('Numeric'));
        $this->assertSame('', $string->most('String'));
    }

    #[Test]
    public function followsPhpArrayKeyRulesForNumericStringCategories(): void
    {
        $classifier = (new Classifier)
            ->learn('First document', '10')
            ->learn('Second document', 10);

        $this->assertSame(10, $classifier->most('First'));
        $this->assertSame(
            [
                'first' => 1,
                'document' => 2,
                'second' => 1,
            ],
            $classifier->getWords('10')
        );
        $this->assertSame($classifier->getWords('10'), $classifier->getWords(10));
    }

    #[Test]
    public function supportsEnumCategoryIdentifiers(): void
    {
        $backed = (new Classifier)
            ->learn('Bright sunny morning', SentimentCategory::Positive)
            ->learn('Dark rainy evening', SentimentCategory::Negative);

        $pure = (new Classifier)
            ->learn('Laravel framework', TopicCategory::Technical)
            ->learn('Family holiday', TopicCategory::Personal);

        $this->assertSame(20, $backed->most('Bright morning'));
        $this->assertSame(
            $backed->getWords(20),
            $backed->getWords(SentimentCategory::Positive)
        );
        $this->assertSame('Technical', $pure->most('Laravel'));
        $this->assertSame(
            $pure->getWords('Technical'),
            $pure->getWords(TopicCategory::Technical)
        );
    }
}
