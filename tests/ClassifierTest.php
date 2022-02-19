<?php

declare(strict_types=1);

namespace AssistedMindfulness\NaiveBayes\Tests;

use AssistedMindfulness\NaiveBayes\Classifier;
use PHPUnit\Framework\TestCase;

class ClassifierTest extends TestCase
{
    public function testMostPossible(): void
    {
        $classifier = new Classifier();

        $classifier
            ->learn('Symfony is the best', 'positive')
            ->learn('PhpStorm is great', 'positive')
            ->learn('Iltar complains a lot', 'negative')
            ->learn('No Symfony is bad', 'negative');

        $this->assertSame('positive', $classifier->mostPossible('Symfony is great'));
        $this->assertSame('negative', $classifier->mostPossible('I complain a lot'));
    }

    /*
        public function testTextDataSetsClassifier(): void
        {
            $classifier = new Classifier();

            $classifier
                ->learn(file_get_contents(__DIR__ . '/datasets/positive-words.txt'), 'positive')
                ->learn(file_get_contents(__DIR__ . '/datasets/negative-words.txt'), 'negative');


            dd(
                $classifier->guess('Symfony is great'),
                $classifier->guess('I complain a bloated')
            );

            $this->assertSame('positive', $classifier->mostPossible('Symfony is great'));
            $this->assertSame('negative', $classifier->mostPossible('I complain a bloated'));


            $this->assertSame('positive', $classifier->mostPossible('I love sunny days'));
            $this->assertSame('negative', $classifier->mostPossible('I hate rain'));
        }*/

    /*
    public function testTextClassifier(): void
    {
        $classifier = new Classifier();

        $classifier
            ->learn(file_get_contents(__DIR__ . '/datasets/training.language.en.txt'), 'English')
            ->learn(file_get_contents(__DIR__ . '/datasets/training.language.fr.txt'), 'French')
            ->learn(file_get_contents(__DIR__ . '/datasets/training.language.de.txt'), 'German');


        $this->assertSame('English', $classifier->mostPossible('I am English'));
        $this->assertSame('French', $classifier->mostPossible('Je suis Français'));
        $this->assertSame('German', $classifier->mostPossible('Ich bin Deutsch'));
    }
    */

    public function testCorrectnessLearn(): void
    {
        $classifier = new Classifier();

        $classifier
            ->learn('amazing, awesome movie!! Yeah!!', 'positive')
            ->learn('Sweet, this is incredibly, amazing, perfect, great!!', 'positive')
            ->learn('terrible, shitty thing. Damn. Sucks!!', 'negative')
            ->learn('I dont really know what to make of this.', 'neutral');

        $this->assertSame('positive', $classifier->mostPossible('awesome, amazing!!.'));
    }

    /*
        public function testCategorizesChineseCorrectly(): void
        {
            $classifier = new Classifier();

            $classifier
                ->learn('Chinese Beijing Chinese', 'chinese')
                ->learn('Chinese Chinese Shanghai', 'chinese')
                ->learn('Chinese Macao', 'chinese')
                ->learn('Tokyo Japan Chinese', 'japanese')
                ->learn('Chinese Macao Beijing Chinese Tokyo Japan', 'chinese');

            $this->assertSame('chinese', $classifier->mostPossible('Chinese Chinese Chinese Chinese Tokyo Japan'));
            $this->assertSame('japanese', $classifier->mostPossible('Tokyo'));
        }
    */

    public function testCategorizesSimpleCorrectly(): void
    {
        $classifier = new Classifier();

        $classifier
            ->learn('I love sunny days', 'positive')
            ->learn('I hate rain', 'negative');

        $this->assertSame('positive', $classifier->mostPossible('is a sunny days'));
        $this->assertSame('negative', $classifier->mostPossible('there will be rain'));
    }

    public function testCategorizesTopicsCorrectly(): void
    {
        $classifier = new Classifier();

        $classifier
            ->learn('not to eat too much is not enough to lose weight', 'health')
            ->learn('Russia try to invade Ukraine', 'politics')
            ->learn('do not neglect exercise', 'health')
            ->learn('Syria is the main issue, Obama says', 'politics')
            ->learn('eat to lose weight', 'health')
            ->learn('you should not eat much', 'health');

        $this->assertSame('politics', $classifier->mostPossible('Obama is'));
    }
}
