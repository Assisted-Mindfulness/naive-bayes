<?php

declare(strict_types=1);

namespace AssistedMindfulness\NaiveBayes\Tests;

use AssistedMindfulness\NaiveBayes\Classifier;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TokenizerTest extends TestCase
{
    #[Test]
    public function tokenizesMultilingualText(): void
    {
        $classifier = new Classifier;

        $classifier->setTokenizer(
            fn (string $string) => Str::of($string)
                ->lower()
                ->matchAll('/[[:alpha:]]+/u')
        );

        $this->assertSame(
            ['hello', 'how', 'are', 'you'],
            $classifier->tokenize('Hello, how are you?')->all()
        );

        $this->assertSame(
            ['hello', 'how', 'are', 'you'],
            $classifier->tokenize("Hello\n\nHow are you?!")->all()
        );

        $this->assertSame(
            [
                'un',
                'importante',
                'punto',
                'de',
                'inflexión',
                'en',
                'la',
                'historia',
                'de',
                'la',
                'ciencia',
                'filosófica',
                'primitiva',
            ],
            $classifier->tokenize('Un importante punto de inflexión en la historia de la ciencia filosófica primitiva')->all()
        );
    }

    #[Test]
    public function usesCustomPathTokenizer(): void
    {
        $classifier = new Classifier;

        $this->assertSame(
            $classifier,
            $classifier->setTokenizer(
                fn (string $path): array => array_values(
                    array_filter(explode('/', $path))
                )
            )
        );

        $this->assertSame(
            ['usr', 'var', 'log'],
            $classifier->tokenize('/usr/var/log/')->all()
        );
    }

    #[Test]
    public function appliesDefaultTokenizerBoundaries(): void
    {
        $this->assertSame(
            ['cats', 'éléphant', 'naïve'],
            (new Classifier)->tokenize('Cat CATS, ÉLÉPHANT 123 co-op naïve.')->all()
        );
    }

    #[Test]
    public function acceptsGeneratorTokenizers(): void
    {
        $classifier = (new Classifier)
            ->setTokenizer(function (string $statement): iterable {
                foreach (explode('|', $statement) as $token) {
                    yield $token;
                }
            })
            ->learn('alpha|beta|alpha', 'generated');

        $this->assertSame(
            [
                'alpha' => 2,
                'beta' => 1,
            ],
            $classifier->getWords('generated')
        );
    }
}
