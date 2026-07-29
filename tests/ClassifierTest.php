<?php

declare(strict_types=1);

namespace AssistedMindfulness\NaiveBayes\Tests;

use AssistedMindfulness\NaiveBayes\Classifier;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClassifierTest extends TestCase
{
    #[Test]
    public function returnsWordCountsSafely(): void
    {
        $classifier = (new Classifier)
            ->learn('Bright sunny morning', 'positive');

        $this->assertSame(
            [
                'bright' => 1,
                'sunny' => 1,
                'morning' => 1,
            ],
            $classifier->getWords('positive')
        );

        $this->assertSame([], $classifier->getWords('unknown'));

        $this->assertSame(
            [
                'positive' => [
                    'bright' => 1,
                    'sunny' => 1,
                    'morning' => 1,
                ],
            ],
            $classifier->getWords()
        );
    }

    #[Test]
    public function returnsMostLikelyType(): void
    {
        $classifier = new Classifier;

        $classifier
            ->learn('Symfony is the best', 'positive')
            ->learn('PhpStorm is great', 'positive')
            ->learn('Iltar complains a lot', 'negative')
            ->learn('No Symfony is bad', 'negative');

        $this->assertSame('positive', $classifier->most('Symfony is great'));
        $this->assertSame('negative', $classifier->most('Iltar complains often'));
    }

    #[Test]
    public function detectsTextLanguage(): void
    {
        $classifier = new Classifier;

        $classifier
            ->learn((string) file_get_contents(__DIR__.'/datasets/training.language.en.txt'), 'English')
            ->learn((string) file_get_contents(__DIR__.'/datasets/training.language.fr.txt'), 'French')
            ->learn((string) file_get_contents(__DIR__.'/datasets/training.language.de.txt'), 'German');

        $this->assertSame('English', $classifier->most('I am English'));
        $this->assertSame('French', $classifier->most('Je suis Français'));
        $this->assertSame('German', $classifier->most('Ich bin Deutsch'));

        $englishText = <<<'TEXT'
            A grasshopper spent the summer hopping about in the sun and singing to his heart's content.
            One day, an ant went hurrying by, looking very hot and weary.
            TEXT;

        $frenchText = <<<'TEXT'
            Ils sont très gentils et ils travaillent beaucoup à l’école. Ils vont au collège.
            Leur rêve, c’est de devenir professeur de piano. Paul aime bien embêter sa soeur.
            Comme elle a horreur des insectes, il met de temps en temps un cafard ou une araignée dans la chambre de sa soeur.
            Et c’est toujours pareil. Elle crie très fort et son frère rit beaucoup.
            TEXT;

        $germanText = <<<'TEXT'
            Familie Müller plant ihren Urlaub. Sie geht in ein Reisebüro und lässt sich von einem Angestellten beraten.
            Als Reiseziel wählt sie Mallorca aus. Familie Müller bucht einen Flug auf die Mittelmeerinsel.
            Sie bucht außerdem zwei Zimmer in einem großen Hotel direkt am Strand. Familie Müller badet gerne im Meer.
            TEXT;

        $this->assertSame('English', $classifier->most($englishText));
        $this->assertSame('French', $classifier->most($frenchText));
        $this->assertSame('German', $classifier->most($germanText));
    }

    #[Test]
    public function learnsFromBalancedAndUnevenDatasets(): void
    {
        $classifier = new Classifier;

        $classifier
            ->learn('amazing, awesome movie!! Yeah!!', 'positive')
            ->learn('Sweet, this is incredibly, amazing, perfect, great!!', 'positive')
            ->learn('terrible, shitty thing. Damn. Sucks!!', 'negative')
            ->learn('I dont really know what to make of this.', 'neutral');

        $this->assertSame('positive', $classifier->most('awesome, amazing!!.'));

        $classifier->uneven();

        $this->assertSame('positive', $classifier->most('awesome, cool, amazing Yeah.'));
    }

    #[Test]
    public function countsWordsAndCategorizesDocuments(): void
    {
        $classifier = new Classifier;

        $classifier
            ->uneven(true)
            ->learn('Chinese Beijing Chinese', 'chinese')
            ->learn('Chinese Chinese Shanghai', 'chinese')
            ->learn('Chinese Macao', 'chinese');

        $classifier->learn('Tokyo Japan Chinese', 'japanese');

        $chineseFrequencyCount = $classifier->getWords('chinese');

        $this->assertSame(5, $chineseFrequencyCount['chinese']);
        $this->assertSame(1, $chineseFrequencyCount['beijing']);
        $this->assertSame(1, $chineseFrequencyCount['shanghai']);
        $this->assertSame(1, $chineseFrequencyCount['macao']);

        $japaneseFrequencyCount = $classifier->getWords('japanese');

        $this->assertSame(1, $japaneseFrequencyCount['tokyo']);
        $this->assertSame(1, $japaneseFrequencyCount['japan']);
        $this->assertSame(1, $japaneseFrequencyCount['chinese']);

        $this->assertSame('chinese', $classifier->most('Chinese Macao Tokyo'));
    }

    #[Test]
    public function categorizesSimpleSentences(): void
    {
        $classifier = new Classifier;

        $classifier
            ->learn('I love sunny days', 'positive')
            ->learn('I hate rain', 'negative');

        $this->assertSame('positive', $classifier->most('is a sunny days'));
        $this->assertSame('negative', $classifier->most('there will be rain'));

        $classifier = new Classifier;

        $classifier
            ->learn('Fun times were had by all', 'positive')
            ->learn('sad dark rainy day in the cave', 'negative');

        $this->assertSame('negative', $classifier->most('there will be dark rain'));
    }

    #[Test]
    public function categorizesTopics(): void
    {
        $classifier = new Classifier;

        $classifier
            ->learn('not to eat too much is not enough to lose weight', 'health')
            ->learn('Russia try to invade Ukraine', 'politics')
            ->learn('do not neglect exercise', 'health')
            ->learn('Syria is the main issue, Obama says', 'politics')
            ->learn('eat to lose weight', 'health')
            ->learn('you should not eat much', 'health');

        $this->assertSame('politics', $classifier->most('Obama is'));
    }

    #[Test]
    public function detectsSpam(): void
    {
        $classifier = new Classifier;

        $classifier->setTokenizer(
            fn (string $string) => Str::of($string)
                ->lower()
                ->matchAll('/[[:alpha:]]+/u')
        );

        $classifier
            ->learn('Learn how to grow your business with these proven strategies', 'ham')
            ->learn('Unlock the secrets of successful investing in our latest guide', 'ham')
            ->learn('Get exclusive access to limited-time discounts and offers', 'spam')
            ->learn('Earn money from home with our easy-to-follow program', 'spam');

        $this->assertSame(
            'ham',
            $classifier->most('Discover the art of effective communication in our workshop')
        );

        $this->assertSame(
            'spam',
            $classifier->most('Start making money from home today with our revolutionary system')
        );
    }
}
