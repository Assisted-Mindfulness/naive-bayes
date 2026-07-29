# Naive Bayes

[![Tests](https://github.com/Assisted-Mindfulness/naive-bayes/actions/workflows/tests.yml/badge.svg)](https://github.com/Assisted-Mindfulness/naive-bayes/actions/workflows/tests.yml)

This PHP package for Naive Bayes works by looking at a training set and making a guess based on that set.
 It uses simple statistics and a bit of math to calculate the result.

## What can I use this for?

You can use this for categorizing any text content into any arbitrary set of **categories**. For example:

- is an email **spam**, or **not spam** ?
- is a news article about **technology**, **politics**, or **sports** ?
- is a piece of text expressing **positive** emotions, or **negative** emotions?

## Installation

You may install Naive Bayes into your project using the Composer package manager:

```bash
composer require assisted-mindfulness/naive-bayes
```

## Learning

Before the algorithm can do anything, it requires a training set with historical information. To teach your classifier which category the text belongs to, call the `learn` method:

```php
$classifier = new Classifier();

$classifier
    ->learn('I love sunny days', 'positive')
    ->learn('I hate rain', 'negative');
```

Categories may be strings, integers, backed enums, or pure enums. Enum cases are normalized to their backing value or,
for pure enums, their case name:

```php
enum Sentiment: int
{
    case Negative = 0;
    case Positive = 1;
}

$classifier->learn('I love sunny days', Sentiment::Positive);
```

The keys returned by `guess()` and the value returned by `most()` use the normalized string or integer identifier.
As with native PHP arrays, integer-like string keys such as `"10"` are normalized to the integer `10`.

## Guessing

After you have trained the classifier, you can use the prediction of which category the transmitted text belongs to, for example:

```php
$classifier->most('is a sunny days'); // positive
$classifier->most('there will be rain'); // negative
```

To inspect the score for every category, use:

```php
$classifier->guess('is a sunny days');

/*
items: array:2 [
  "negative" => 0.2461538461538462
  "positive" => 0.7538461538461538
]
*/
```

Probabilities are returned as `Brick\Math\BigDecimal` values and always add up to exactly `1` at the configured decimal
scale. Any rounding remainder is assigned deterministically to the highest-ranked categories. The classifier applies
Laplace smoothing to words learned in other categories and ignores words that do not occur anywhere in the training
data.

## Uneven

Categories have equal prior probabilities by default. If the number of learned documents should influence the result,
enable uneven mode to calculate Laplace-smoothed priors from the training distribution.

```php
$classifier
   ->uneven()
   ->guess('is a sunny days');
```

## Precision

Returned probabilities use 16 decimal places by default. You can increase or reduce the precision through the fluent
`scale` method:

```php
$classifier
    ->scale(32)
    ->learn('I love sunny days', 'positive')
    ->guess('A sunny day');
```

The scale must be a positive integer.

## Tokenizer

The algorithm utilizes a tokenizer to segment text into words. By default, it extracts Unicode letters, converts them to
lowercase, and includes words longer than three characters. You can also define a custom tokenizer:

```php
$classifier = new Classifier();

$classifier
    ->setTokenizer(function (string $string) {
        return Str::of($string)
            ->lower()
            ->matchAll('/[[:alpha:]]+/u')
            ->filter(fn (string $word) => Str::length($word) > 3);
    })
    ->learn('I love sunny days', 'positive');
```

The tokenizer may return any iterable of strings.

## Development

Run the complete fast quality suite:

```bash
composer check
```

Run mutation testing with a required MSI of 90%:

```bash
composer mutation
```

The mutation suite measures observable behavior. Protected visibility changes are excluded, while subclass
customization is covered by a dedicated behavioral test.

## Wrapping up

There you have it! Even with a **very** small training set the algorithm can still return some decent results. For example, [Naive Bayes has been proven to give decent results in sentiment analyses](http://www-nlp.stanford.edu/courses/cs224n/2009/fp/3.pdf).

Moreover, Naive Bayes can be applied to more than just text. If you have other ways of calculating the probabilities of your metrics, you can plug those in as well.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
