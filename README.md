# typed-arrays

--- 

[![Continuous Integration](https://github.com/boesing/typed-arrays/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/boesing/typed-arrays/actions/workflows/continuous-integration.yml)
[![type-coverage](https://shepherd.dev/github/boesing/typed-arrays/coverage.svg)](https://shepherd.dev/github/boesing/typed-arrays)
[![psalm](https://shepherd.dev/github/boesing/typed-arrays/level.svg)](https://shepherd.dev/github/boesing/typed-arrays)

Totally typed library to work with lists or maps.


## Installation

To use this library in your project, please install it via composer:

```
$ composer require boesing/typed-arrays
```

## Usage

The main reason why this library was created was the fact, that *every* array in PHP is a hashmap.
If you primarily work with APIs, you might have experienced that `json_encode` of an array type sometimes leads to annoying issues.

To get rid of `array` being passed through an application, the `OrderedListInterface` and the `MapInterface` became very handy.
To also provide most if not any of the `array_*` functions to the developers, most of these array functions do have a method within `OrderedListInterface` or `MapInterface`.

### Common mistakes

Lets take some real-world use cases to better reflect the idea behind this library:

```php
$listOfIntegers = [1, 2, 3, 4];

$myObject = new stdClass();
$myObject->integers = $listOfIntegers;

echo json_encode($myObject) . PHP_EOL;

// Output of the code above will be: `{"integers":[1,2,3,4]}`
// Now some refactoring has to be made since the requirement changed. The requirement now is that the integers list
// must not contain odd values anymore. So `array_filter` to the rescue, right?

$listOfEvenIntegers = array_filter([1, 2, 3, 4], static fn (int $integer): int => $integer % 2 === 0);

$myObject = new stdClass();
$myObject->integers = $listOfEvenIntegers;

echo json_encode($myObject) . PHP_EOL; 

// Output of the refactored code above now became: `{"integers":{"1":2,"3":4}}`
// So what now happened is a huge problem for highly type-sensitive API clients since we changed a list to a hashmap
// Same happens with hashmaps which suddenly become empty.

$hashmap = [
    'foo' => 'bar',
];
$myObject = new stdClass();
$myObject->map = $hashmap;

echo json_encode($myObject) . PHP_EOL; 

// Output of the code above will be: `{"map":{"foo":"bar"}}`
// So now some properties are being added, some are being removed, the definition of your API says
// "the object will contain additional properties because heck I do not want to declare every property"
// "so to make it easier, every property has a string value"
// can be easily done with something like this in JSONSchema: `{"type": "object", "additional_properties": {"type": "string"}}`
// Now, some string value might become `null` due to whatever reason, lets say it was a bug and thus the happy path always returned a string
// The most logical way here is, due to our lazyness, to use something like `array_filter` to get rid of all our non-string values

$hashmap = [
    'foo' => null,
];
$myObject = new stdClass();
$myObject->map = array_filter($hashmap);

echo json_encode($myObject) . PHP_EOL;

// Output of the refactored code above now became: `{"map":[]}`
// So in case that every array value is being wiped due to the filtering, we suddenly have a type-change from
// a hashmap to a list. This is ofc also problematic since we do not want to have a list here but an empty object like
// so: `{"map":{}}`
```

_(The above example can be verified on 3v4l.org - a PHP sandbox: https://3v4l.org/Gfogn#v8.1.6)_

### typed-arrays to the rescue

So with this library, one is a little bit more type-safe when it comes to array handling.
However, the `MapInterface` actually will become `null` within a `json_encode` in case it is empty.

So lets take the above example in combination with our factories:

```php

use Boesing\TypedArrays\TypedArrayFactory;
$factory = new TypedArrayFactory();

$listOfIntegers = $factory->createOrderedList([1, 2, 3, 4]);

$myObject = new stdClass();
$myObject->integers = $listOfIntegers;

echo json_encode($myObject) . PHP_EOL;

// Output of the code above will be: `{"integers":[1,2,3,4]}`
// Now some refactoring has to be made since the requirement changed. The requirement now is that the integers list
// must not contain odd values anymore. So `array_filter` to the rescue, right?

$listOfEvenIntegers = $factory->createOrderedList([1, 2, 3, 4])->filter(static fn (int $integer): int => $integer % 2 === 0);

$myObject = new stdClass();
$myObject->integers = $listOfEvenIntegers;

echo json_encode($myObject) . PHP_EOL; 

// Output of the refactored code above now became: `{"integers":[2, 4]}`
// Due to the internal handling of `array_filter`, the `OrderedListInterface` won't change its type.

// Even hashmaps can be filtered, the type stays the same but in case of an empty map, `null` is being passed to the JSON object
$hashmap = $factory->createMap([
    'foo' => 'bar',
]);
$myObject = new stdClass();
$myObject->map = $hashmap;

echo json_encode($myObject) . PHP_EOL; 

// Output of the code above will be: `{"map":{"foo":"bar"}}`
// So now some properties are being added, some are being removed, the definition of your API says
// "the object will contain additional properties because heck I do not want to declare every property"
// "so to make it easier, every property has a string value"
// can be easily done with something like this in JSONSchema: `{"type": "object", "additional_properties": {"type": "string"}}`
// Now, some string value might become `null` due to whatever reason, lets say it was a bug and thus the happy path always returned a string
// The most logical way here is, due to our lazyness, to use something like `array_filter` to get rid of all our non-string values

$hashmap = $factory->createMap([
    'foo' => null,
]);
$myObject = new stdClass();
$myObject->map = $hashmap->filter(static fn ($value) => $value !== null);

echo json_encode($myObject) . PHP_EOL;

// Output of the refactored code above now became: `{"map":null}`
// So in case that every array value is being wiped due to the filtering, we suddenly have a type-change from
// a hashmap to a list. This is ofc also problematic since we do not want to have a list here but an empty object like
// so: `{"map":{}}`
```

### Conclusion

When it comes to API responses, you might not want to rely on PHP array structure. Always prefer real objects with real 
properties and real property type-hints over `non-empty-array`.
