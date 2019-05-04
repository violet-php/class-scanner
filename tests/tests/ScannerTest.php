<?php

namespace Violet\ClassScanner;

use PHPUnit\Framework\Constraint\IsIdentical;
use PHPUnit\Framework\TestCase;
use PHPUnit\Util\InvalidArgumentHelper;
use Sample\Space\SecondTopClass;
use Sample\Space\SubClass;
use Sample\Space\TopClass;
use Violet\ClassScanner\Exception\ClassScannerException;
use Violet\ClassScanner\Exception\FileNotFoundException;
use Violet\ClassScanner\Exception\UndefinedClassException;
use Violet\ClassScanner\Tests\ClassLoader;
use Violet\ClassScanner\Tests\SampleException;
use Violet\ClassScanner\Tests\SampleTrait;

/**
 * ScannerTest.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2019 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class ScannerTest extends TestCase
{
    public function testBasicClassScanning(): void
    {
        $scanner = new Scanner();
        $classes = $scanner->parse('<?php class FooBar { }');

        $this->assertValues(['FooBar'], $classes);
        $this->assertValues(['FooBar'], $scanner->getClasses());
        $this->assertValues(['FooBar'], $scanner->getClasses(Scanner::T_CLASS));
        $this->assertValues([], $scanner->getClasses(Scanner::T_ABSTRACT));
        $this->assertValues([], $scanner->getClasses(Scanner::T_INTERFACE));
        $this->assertValues([], $scanner->getClasses(Scanner::T_TRAIT));
        $this->assertValues([], $scanner->getSubClasses('FooBar'));
        $this->assertValues([], $scanner->getSubClasses('Foo'));
        $this->assertValues([], $scanner->getFiles(['FooBar']));
    }

    public function testBasicInheritance(): void
    {
        $scanner = new Scanner();
        $scanner->parse('<?php class FooBar extends Foo { }');
        $scanner->parse('<?php Class Foo { }');

        $this->assertValues(['FooBar', 'Foo'], $scanner->getClasses());
        $this->assertValues(['FooBar'], $scanner->getSubClasses('Foo'));
        $this->assertValues([], $scanner->getSubClasses('FooBar'));
    }

    public function testMissingLink(): void
    {
        $scanner = new Scanner();
        $scanner->parse('<?php class FooIterator extends ArrayIterator { }');

        $this->assertValues(['FooIterator'], $scanner->getSubClasses(\Traversable::class));
    }

    public function testHierarchy(): void
    {
        $scanner = new Scanner();
        $scanner->parse(
            <<<'PHP'
<?php

interface ParentOnlyInterface extends Throwable { }
interface ParentInterface extends Throwable { }
interface ChildInterface extends ParentInterface { }
trait ParentOnlyTrait { }
trait ParentTrait { }
trait ChildTrait { use ParentTrait; }
abstract class ParentException extends Exception implements ParentOnlyInterface { use ParentOnlyTrait; }
class ChildException extends ParentException implements ChildInterface { use ChildTrait; } 
PHP
);

        $this->assertValues([
            'ParentOnlyInterface',
            'ParentInterface',
            'ChildInterface',
            'ParentOnlyTrait',
            'ParentTrait',
            'ChildTrait',
            'ParentException',
            'ChildException',
        ], $scanner->getClasses());

        $this->assertValues(['ChildException'], $scanner->getClasses(Scanner::T_CLASS));
        $this->assertValues(['ParentException'], $scanner->getClasses(Scanner::T_ABSTRACT));
        $this->assertValues(
            ['ParentOnlyInterface', 'ParentInterface', 'ChildInterface'],
            $scanner->getClasses(Scanner::T_INTERFACE)
        );
        $this->assertValues(['ParentOnlyTrait', 'ParentTrait', 'ChildTrait'], $scanner->getClasses(Scanner::T_TRAIT));

        $this->assertValues(['ChildException'], $scanner->getSubClasses('ParentOnlyInterface'));
        $this->assertValues(['ChildException'], $scanner->getSubClasses('ParentInterface'));
        $this->assertValues(['ChildException'], $scanner->getSubClasses('ChildInterface'));
        $this->assertValues(['ChildException'], $scanner->getSubClasses('ParentOnlyTrait'));
        $this->assertValues(['ChildException'], $scanner->getSubClasses('ParentTrait'));
        $this->assertValues(['ChildException'], $scanner->getSubClasses('ChildTrait'));
        $this->assertValues(['ChildException'], $scanner->getSubClasses('ParentException'));

        $this->assertValues(
            ['ParentException', 'ChildException'],
            $scanner->getSubClasses('ParentOnlyInterface', Scanner::T_ALL)
        );
        $this->assertValues(
            ['ChildInterface', 'ChildException'],
            $scanner->getSubClasses('ParentInterface', Scanner::T_ALL)
        );
        $this->assertValues(
            ['ChildException'],
            $scanner->getSubClasses('ChildInterface', Scanner::T_ALL)
        );
        $this->assertValues(
            ['ParentException', 'ChildException'],
            $scanner->getSubClasses('ParentOnlyTrait', Scanner::T_ALL)
        );
        $this->assertValues(
            ['ChildTrait', 'ChildException'],
            $scanner->getSubClasses('ParentTrait', Scanner::T_ALL)
        );
        $this->assertValues(
            ['ChildException'],
            $scanner->getSubClasses('ChildTrait', Scanner::T_ALL)
        );
        $this->assertValues(
            ['ChildException'],
            $scanner->getSubClasses('ParentException', Scanner::T_ALL)
        );
    }

    public function testComplexParsing(): void
    {
        $scanner = new Scanner();
        $scanner->parse(
            <<<'PHP'
<?php
namespace Foo;
class FooClass { }
namespace Bar;
use Foo\FooClass as ClassFromFoo;
use Foo\{FooClass as Foos};
if (true) { class BarClass extends ClassFromFoo { }}
if (true) { class BarBarClass extends Foos { }}
class FooBarClass extends \Foo\FooClass { }
PHP
);

        $this->assertValues(
            ['Bar\BarClass', 'Bar\BarBarClass', 'Bar\FooBarClass'],
            $scanner->getSubClasses('Foo\FooClass')
        );
    }

    public function testNoAnonymousClasses(): void
    {
        $scanner = new Scanner();
        $scanner->parse('<?php class Foo extends Exception { } $bar = new class extends Exception { };');
        $this->assertValues(['Foo'], $scanner->getSubClasses('Exception'));
    }

    public function testReflectionLoading(): void
    {
        $scanner = new Scanner();
        $scanner->parse('<?php class Foo extends ' . SampleException::class . ' { } ');
        $scanner->allowAutoloading();

        $this->assertValues(['Foo'], $scanner->getSubClasses(\Exception::class));
        $this->assertValues(['Foo'], $scanner->getSubClasses(SampleTrait::class));
    }

    public function testIgnoreMissingClasses(): void
    {
        $scanner = new Scanner();
        $scanner->parse('<?php class Foo extends LogicException { } class Bar extends Exception { }');

        $scanner->ignoreMissing();
        $this->assertValues(['Bar'], $scanner->getSubClasses(\Exception::class));

        $scanner->ignoreMissing(false);
        $this->assertValues(['Foo', 'Bar'], $scanner->getSubClasses(\Exception::class));
    }

    public function testParsingFilePath(): void
    {
        $scanner = new Scanner();
        $scanner->scanFile(TEST_FILES_DIRECTORY . '/TopClass.php');

        $this->assertSame([TopClass::class], $scanner->getClasses());
    }

    public function testParsingDirectory(): void
    {
        $scanner = new Scanner();
        $scanner->scanDirectory(TEST_FILES_DIRECTORY);

        $this->assertValues([TopClass::class, SecondTopClass::class], $scanner->getClasses());
    }

    public function testScanningFilePaths(): void
    {
        $scanner = new Scanner();
        $scanner->scan([
            TEST_FILES_DIRECTORY . '/TopClass.php',
            new \SplFileInfo(TEST_FILES_DIRECTORY . '/sub/SubClass.php'),
        ]);

        $this->assertValues([TopClass::class, SubClass::class], $scanner->getClasses());
        $this->assertValues([TEST_FILES_DIRECTORY . '/TopClass.php'], $scanner->getFiles([TopClass::class]));
        $this->assertValues([TEST_FILES_DIRECTORY . '/sub/SubClass.php'], $scanner->getFiles([SubClass::class]));
    }

    public function testFileNotFoundError(): void
    {
        $scanner = new Scanner();

        $this->expectException(FileNotFoundException::class);
        $scanner->scanFile(TEST_FILES_DIRECTORY . '/PathToUndefinedFile');
    }

    public function testMissingLinkFile(): void
    {
        $temp = tempnam(sys_get_temp_dir(), 'test');
        unlink($temp);
        symlink(TEST_FILES_DIRECTORY . '/PathToUndefinedFile', $temp);

        try {
            $scanner = new Scanner();
            $scanner->scanFile($temp);

            $this->assertValues([], $scanner->getClasses());
        } finally {
            unlink($temp);
        }
    }

    public function testAutoloadingSuccess(): void
    {
        $class = $this->generateDynamicClass('SuccessTest');
        $autoloader = new ClassLoader($class, "class $class { }");
        spl_autoload_register([$autoloader, 'autoload']);

        try {
            $scanner = new Scanner();
            $scanner->parse("<?php class Foo Extends $class { }");
            $scanner->allowAutoloading();

            $this->assertValues(['Foo'], $scanner->getSubClasses($class));
        } finally {
            spl_autoload_unregister([$autoloader, 'autoload']);
        }
    }

    public function testAutoloadingFailure(): void
    {
        $class = $this->generateDynamicClass('FailureTest');
        $autoloader = $this->getMockBuilder(ClassLoader::class)
            ->disableOriginalConstructor()
            ->getMock();
        $autoloader->expects($this->never())->method('autoload');

        spl_autoload_register([$autoloader, 'autoload']);
        $result = null;

        try {
            $scanner = new Scanner();
            $scanner->parse("<?php class Foo Extends $class { }");
            $scanner->getSubClasses($class);
        } catch (ClassScannerException $exception) {
            $result = $exception;
        } finally {
            spl_autoload_unregister([$autoloader, 'autoload']);
        }

        $this->assertInstanceOf(UndefinedClassException::class, $result);
    }

    private function generateDynamicClass($prefix): string
    {
        $iteration = 0;

        do {
            $name = sprintf('DynamicClass_%s_%s', $prefix, $iteration++);
        } while (class_exists($name, false));

        return $name;
    }

    private function assertValues($expected, $actual, string $message = ''): void
    {
        if (!\is_array($expected)) {
            throw InvalidArgumentHelper::factory(1, 'array');
        }

        if (! \is_array($actual)) {
            throw InvalidArgumentHelper::factory(2, 'array');
        }

        sort($expected);
        sort($actual);

        $this->assertThat($actual, new IsIdentical($expected), $message);
    }
}
