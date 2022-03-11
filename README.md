# Naive Bayes

Naive Bayes works by looking at a training set and making a guess based on that set.
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

## Guessing

After you have trained the classifier, you can use the prediction of which category the transmitted text belongs to, for example:

```php
$classifier->most('is a sunny days'); // positive
$classifier->most('there will be rain'); // negative
```

In order for you to enter more similar information, you can use:
```php
$classifier->guess('is a sunny days');

/*
items: array:2 [
  "positive" => 0.0064
  "negative" => 0.0039062
]
*/
```


## Wrapping up

There you have it! Even with a **very** small training set the algorithm can still return some decent results. For example, [Naive Bayes has been proven to give decent results in sentiment analyses](http://www-nlp.stanford.edu/courses/cs224n/2009/fp/3.pdf).

Moreover, Naive Bayes can be applied to more than just text. If you have other ways of calculating the probabilities of your metrics you can also plug those in and it will just as good.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
