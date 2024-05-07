<?php

declare(strict_types=1);

namespace AssistedMindfulness\NaiveBayes\Tests;

use AssistedMindfulness\NaiveBayes\Classifier;
use PHPUnit\Framework\TestCase;

class ClassifierTest extends TestCase
{
    public function testTokenizeClassifier(): void
    {
        $classifier = new Classifier();

        $this->assertEquals(
            ['hello', 'how', 'are', 'you'],
            $classifier->tokenize('Hello, how are you?')->toArray()
        );

        $this->assertEquals(
            ['hello', 'how', 'are', 'you'],
            $classifier->tokenize("Hello\n\nHow are you?!")->toArray()
        );

        $this->assertEquals(
            ['un', 'importante', 'punto', 'de', 'inflexión', 'en', 'la', 'historia', 'de', 'la', 'ciencia', 'filosófica', 'primitiva'],
            $classifier->tokenize("Un importante punto de inflexión en la historia de la ciencia filosófica primitiva")->toArray()
        );
    }

    public function testCustomTokenizeClassifier(): void
    {
        $classifier = new Classifier();

        $classifier->setTokenizer(
            fn ($str) => array_values(array_filter(explode('/', $str)))
        );

        $this->assertEquals(
            ['usr', 'var', 'log'],
            $classifier->tokenize('/usr/var/log/')->toArray()
        );
    }

    public function testMostClassifier(): void
    {
        $classifier = new Classifier();

        $classifier
            ->learn('Symfony is the best', 'positive')
            ->learn('PhpStorm is great', 'positive')
            ->learn('Iltar complains a lot', 'negative')
            ->learn('No Symfony is bad', 'negative');

        $this->assertSame('positive', $classifier->most('Symfony is great'));
        $this->assertSame('negative', $classifier->most('I complain a lot'));
    }

    /*
    public function testTextDataSetsClassifier(): void
    {
        $classifier = new Classifier();

        $classifier
            ->learn(file_get_contents(__DIR__ . '/datasets/positive-words.txt'), 'positive')
            ->learn(file_get_contents(__DIR__ . '/datasets/negative-words.txt'), 'negative');

        // Test for a sentence containing positive words
        $this->assertSame('positive', $classifier->most('The movie was absolutely fantastic and uplifting.'));

        // Test for a sentence containing negative words
        //$this->assertSame('negative', $classifier->most('The service at the restaurant was terrible, and the food was awful.'));}
    */

    public function testTextClassifier(): void
    {
        $classifier = new Classifier();

        $classifier
            ->learn((string) file_get_contents(__DIR__ . '/datasets/training.language.en.txt'), 'English')
            ->learn((string) file_get_contents(__DIR__ . '/datasets/training.language.fr.txt'), 'French')
            ->learn((string) file_get_contents(__DIR__ . '/datasets/training.language.de.txt'), 'German');


        $this->assertSame('English', $classifier->most('I am English'));
        $this->assertSame('French', $classifier->most('Je suis Français'));
        $this->assertSame('German', $classifier->most('Ich bin Deutsch'));


        $englishText = "A grasshopper spent the summer hopping about in the sun and singing to his heart's content.
         One day, an ant went hurrying by, looking very hot and weary.";

        $frenchText = 'Ils sont très gentils et ils travaillent beaucoup à l’école. Ils vont au collège.
         Leur rêve, c’est de devenir professeur de piano. Paul aime bien embêter sa soeur.
          Comme elle a horreur des insectes, il met de temps en temps un cafard ou une araignée dans la chambre de sa soeur.
           Et c’est toujours pareil. Elle crie très fort et son frère rit beaucoup.';

        $germanText = "Familie Müller plant ihren Urlaub. Sie geht in ein Reisebüro und lässt sich von einem Angestellten beraten.
         Als Reiseziel wählt sie Mallorca aus. Familie Müller bucht einen Flug auf die Mittelmeerinsel. 
         Sie bucht außerdem zwei Zimmer in einem großen Hotel direkt am Strand. Familie Müller badet gerne im Meer.";


        $this->assertSame('English', $classifier->most($englishText));
        $this->assertSame('French', $classifier->most($frenchText));
        $this->assertSame('German', $classifier->most($germanText));
    }

    public function testCorrectnessLearn(): void
    {
        $classifier = new Classifier();

        $classifier
            ->learn('amazing, awesome movie!! Yeah!!', 'positive')
            ->learn('Sweet, this is incredibly, amazing, perfect, great!!', 'positive')
            ->learn('terrible, shitty thing. Damn. Sucks!!', 'negative')
            ->learn('I dont really know what to make of this.', 'neutral');

        $this->assertSame('positive', $classifier->most('awesome, amazing!!.'));

        $classifier->uneven();

        $this->assertSame('positive', $classifier->most('awesome, cool, amazing Yeah.'));
    }

    public function testWordCountCorrectly(): void
    {
        $classifier = new Classifier();

        $classifier
            ->uneven(true)
            ->learn('Chinese Beijing Chinese', 'chinese')
            ->learn('Chinese Chinese Shanghai', 'chinese')
            ->learn('Chinese Macao', 'chinese');

        // teach it how to identify the `japanese` category
        $classifier->learn('Tokyo Japan Chinese', 'japanese');

        // make sure it learned the `chinese` category correctly
        $chineseFrequencyCount = $classifier->getWords('chinese');

        $this->assertTrue($chineseFrequencyCount['chinese'] === 5);
        $this->assertTrue($chineseFrequencyCount['beijing'] === 1);
        $this->assertTrue($chineseFrequencyCount['shanghai'] === 1);
        $this->assertTrue($chineseFrequencyCount['macao'] === 1);


        // make sure it learned the `japanese` category correctly
        $japaneseFrequencyCount = $classifier->getWords('japanese');

        $this->assertTrue($japaneseFrequencyCount['tokyo'] === 1);
        $this->assertTrue($japaneseFrequencyCount['japan'] === 1);
        $this->assertTrue($japaneseFrequencyCount['chinese'] === 1);


        // Verify that the classifier correctly categorizes a new document
        $this->assertSame('chinese', $classifier->most('Chinese Macao Tokyo'));
    }

    public function testCategorizesSimpleCorrectly(): void
    {
        $classifier = new Classifier();

        $classifier
            ->learn('I love sunny days', 'positive')
            ->learn('I hate rain', 'negative');

        $this->assertSame('positive', $classifier->most('is a sunny days'));
        $this->assertSame('negative', $classifier->most('there will be rain'));


        $classifier = new Classifier();

        $classifier
            ->learn('Fun times were had by all', 'positive')
            ->learn('sad dark rainy day in the cave', 'negative');

        $this->assertSame('negative', $classifier->most('is a sunny days'));
        $this->assertSame('negative', $classifier->most('there will be dark rain'));
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

        $this->assertSame('politics', $classifier->most('Obama is'));
    }

    public function testSimpleSpam(): void
    {
        $classifier = new Classifier();

        $classifier
            ->learn('Learn how to grow your business with these proven strategies', 'ham')
            ->learn('Unlock the secrets of successful investing in our latest guide', 'ham')
            ->learn('Get exclusive access to limited-time discounts and offers', 'spam')
            ->learn('Earn money from home with our easy-to-follow program', 'spam');


        $this->assertEquals('ham', $classifier->most('Discover the art of effective communication in our workshop'));
        $this->assertEquals('spam', $classifier->most('Start making money from home today with our revolutionary system'));
    }
}
