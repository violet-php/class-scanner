# PHP File Class Scanner

The Class Scanner library provides a convenient interface for finding classes
defined in PHP source code. The purpose of this library is to be able to find
classes within a class hierarchy without the need of actually executing the code
and loading the classes into memory.

Normally if you want to find, for example, all child classes for a specific
class in PHP, you would probably first need to include all the files that
contain all the possible classes and then construct a hierarchy using class
reflection by inspect all defined classes. Sometimes, however, including every
file may not be possible, or may even be dangerous.

This library offers an alternative by allowing you to scan files for class
definitions without the need of executing them in order to find classes based on
their hierarchy or just for determining what classes are defined in which files.

Api documentation is available at: https://docs.riimu.net/violet/class-scanner

## Requirements

  * PHP Version 7.1 or newer
  * The [tokenizer][tokenizer] extension must not be disabled

## Installation

Installation of this library is supported via composer. To install this library
in your project, follow these steps:

  1. Install composer by following [composer download instructions][composer]
  2. Add this library to your project by running `composer require violet/class-scanner`
  
## Usage

The basic question this library intends to help answer, is which classes in your
codebase extend a specific class.

Let's say, for example, you want to find all classes in your project that extend
that class `Application\Controller`. In order to do this, you could do:

```php
<?php

require 'vendor/autoload.php';

$scanner = new Violet\ClassScanner\Scanner();
$scanner->scanDirectory('src');

foreach ($scanner->getSubClasses(Application\Controller::class) as $name) {
    echo $name;
}
```

### The scanner class

#### Scanning files

The scanner class provides the basic functionality of the library. For scanning
class definitions from source code, it provides the following methods:

  * `scanFile(string $filename)` - Scans this given filename for definitions
  * `scanDirectory(string $directory)` - Scans all the files in the directory
    for definitions, but does not travers directories recursively
  * `scan(iterable $files)` - Takes an iterable of file paths or instances of
    `SplFileObject` to scan.
  * `parse(string $code)` - Parses the given string as PHP code to scan for
    class definitions.
    
All the scan* functions returns the scanner itself, so you can use it like a
fluent interface, e.g.

```php
<?php

require 'vendor/autoload.php';

$classes = (new Violet\ClassScanner\Scanner())
    ->scanDirectory('controllers')
    ->scanDirectory('reporting')
    ->getClasses();
```

#### Getting class names

The `parse()` function, however, returns list of all the `TypeDefinition`
instances from the parsed code.

To get the scanned classes, the scanner has two methods:

  * `getClasses(int $filter = TypeDefinition::TYPE_ANY)` - Returns the names
    of all classes from the parsed files. The filter indicates the types of
    definitions to return.
  * `getSubClasses(string $class, int $filter = TypeDefinition::TYPE_CLASS)` -
    Returns the names of all the child classes (inspected recursively) for the
    given class name. The second parameter allows to filter the types of
    returned names.
    
By default, the `getClasses()` returns all type definitions from the files. This
includes classes, abstract classes, interfaces and traits. Both `getClasses()`
and `getSubClasses()` have a filter parameter to return only specific kinds of
types. The allowed types are:

  * `Violet\Scanner\TypeDefinition::TYPE_CLASS` - Filters only instantiable
    classes and does not include abstract classes
  * `Violet\Scanner\TypeDefinition::TYPE_ABSTRACT` - Filters for abstract class
    definitions
  * `Violet\Scanner\TypeDefinition::TYPE_INTERFACE` - Filters for interfaces
  * `Violet\Scanner\TypeDefinition::TYPE_TRAIT` - Filters for traits
  * `Violet\Scanner\TypeDefinition::TYPE_ANY` - Filters for any type
  
The types can be combined with binary or operator, e.g.
`TypeDefinition::TYPE_CLASS | TypeDefinition::TYPE_ABSTRACT`

#### Type definitions

If you need to implement more complex logic, you may want to use the
`TypeDefintion` objects created from the parsed code. You can use the method
`getDefinitions(array $classes)` to get definitions for the listed classes.

To get all definition from the scanned files you can use, for example:

```php
$definitions = $scanner->getDefinitions($scanner->getClasses());
```

Note that even if you provide only one name, the array may contain multiple
type definitions if the same type is defined multiple times in the scanned
files.

### Scanning all sub directories and excluding specific paths

By default, the scanner 
  
[tokenizer]: https://www.php.net/manual/en/tokenizer.installation.php
[composer]: https://getcomposer.org/download/
