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
use Violet\ClassScanner\Exception\ParsingException;
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
        $definitions = $scanner->parse('<?php class FooBar { }');

        $this->assertCount(1, $definitions);
        $this->assertSame('FooBar', reset($definitions)->getName());
        $this->assertValues(['FooBar'], $scanner->getClasses());
        $this->assertValues(['FooBar'], $scanner->getClasses(TypeDefinition::TYPE_CLASS));
        $this->assertValues([], $scanner->getClasses(TypeDefinition::TYPE_ABSTRACT));
        $this->assertValues([], $scanner->getClasses(TypeDefinition::TYPE_INTERFACE));
        $this->assertValues([], $scanner->getClasses(TypeDefinition::TYPE_TRAIT));
        $this->assertValues([], $scanner->getSubClasses('FooBar'));
        $this->assertValues([], $scanner->getSubClasses('Foo'));
        $this->assertValues($definitions, $scanner->getDefinitions($scanner->getClasses()));
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
        $definitionTypes = [
            'ParentOnlyInterface' => TypeDefinition::TYPE_INTERFACE,
            'ParentInterface' => TypeDefinition::TYPE_INTERFACE,
            'ChildInterface' => TypeDefinition::TYPE_INTERFACE,
            'ParentOnlyTrait' => TypeDefinition::TYPE_TRAIT,
            'ParentTrait' => TypeDefinition::TYPE_TRAIT,
            'ChildTrait' => TypeDefinition::TYPE_TRAIT,
            'ParentException' => TypeDefinition::TYPE_ABSTRACT,
            'ChildException' => TypeDefinition::TYPE_CLASS,
        ];

        $classes = $scanner->getClasses();
        $this->assertValues(array_keys($definitionTypes), $classes);

        $definitions = $scanner->getDefinitions($classes);
        $this->assertCount(\count($definitionTypes), $definitions);

        foreach ($definitions as $definition) {
            $name = $definition->getName();
            $this->assertArrayHasKey($name, $definitionTypes);
            $this->assertSame($definitionTypes[$name], $definition->getType());
            unset($definitionTypes[$name]);
        }

        $this->assertCount(0, $definitionTypes);

        $definitions = $scanner->getDefinitions(['ChildException']);
        $type = current($definitions);

        $this->assertCount(1, $definitions);
        $this->assertInstanceOf(TypeDefinition::class, $type);
        $this->assertSame('ParentException', $type->getParentName());
        $this->assertValues(['ChildInterface'], $type->getInterfaceNames());
        $this->assertValues(['ChildTrait'], $type->getTraitNames());
        $this->assertValues(['ParentException', 'ChildInterface', 'ChildTrait'], $type->getAllNames());

        $this->assertValues(['ChildException'], $scanner->getClasses(TypeDefinition::TYPE_CLASS));
        $this->assertValues(['ParentException'], $scanner->getClasses(TypeDefinition::TYPE_ABSTRACT));
        $this->assertValues(
            ['ParentOnlyInterface', 'ParentInterface', 'ChildInterface'],
            $scanner->getClasses(TypeDefinition::TYPE_INTERFACE)
        );
        $this->assertValues(
            ['ParentOnlyTrait', 'ParentTrait', 'ChildTrait'],
            $scanner->getClasses(TypeDefinition::TYPE_TRAIT)
        );

        $this->assertValues(['ChildException'], $scanner->getSubClasses('ParentOnlyInterface'));
        $this->assertValues(['ChildException'], $scanner->getSubClasses('ParentInterface'));
        $this->assertValues(['ChildException'], $scanner->getSubClasses('ChildInterface'));
        $this->assertValues(['ChildException'], $scanner->getSubClasses('ParentOnlyTrait'));
        $this->assertValues(['ChildException'], $scanner->getSubClasses('ParentTrait'));
        $this->assertValues(['ChildException'], $scanner->getSubClasses('ChildTrait'));
        $this->assertValues(['ChildException'], $scanner->getSubClasses('ParentException'));

        $this->assertValues(
            ['ParentException', 'ChildException'],
            $scanner->getSubClasses('ParentOnlyInterface', TypeDefinition::TYPE_ANY)
        );
        $this->assertValues(
            ['ChildInterface', 'ChildException'],
            $scanner->getSubClasses('ParentInterface', TypeDefinition::TYPE_ANY)
        );
        $this->assertValues(
            ['ChildException'],
            $scanner->getSubClasses('ChildInterface', TypeDefinition::TYPE_ANY)
        );
        $this->assertValues(
            ['ParentException', 'ChildException'],
            $scanner->getSubClasses('ParentOnlyTrait', TypeDefinition::TYPE_ANY)
        );
        $this->assertValues(
            ['ChildTrait', 'ChildException'],
            $scanner->getSubClasses('ParentTrait', TypeDefinition::TYPE_ANY)
        );
        $this->assertValues(
            ['ChildException'],
            $scanner->getSubClasses('ChildTrait', TypeDefinition::TYPE_ANY)
        );
        $this->assertValues(
            ['ChildException'],
            $scanner->getSubClasses('ParentException', TypeDefinition::TYPE_ANY)
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
        require_once TEST_FILES_DIRECTORY . '/../helpers/SampleException.php';
        require_once TEST_FILES_DIRECTORY . '/../helpers/SampleTrait.php';

        $scanner = new Scanner();
        $scanner->parse('<?php class Foo extends ' . SampleException::class . ' { } ');

        $this->assertValues(['Foo'], $scanner->getSubClasses(\Exception::class));
        $this->assertValues(['Foo'], $scanner->getSubClasses(SampleTrait::class));
    }

    public function testParsingFilePath(): void
    {
        $scanner = new Scanner();
        $scanner->scanFile(TEST_FILES_DIRECTORY . '/TopClass.php');

        $this->assertSame([TopClass::class], $scanner->getClasses());
        $this->assertSame(
            TEST_FILES_DIRECTORY . '/TopClass.php',
            current($scanner->getDefinitions([TopClass::class]))->getFilename()
        );
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

        $paths = array_map(function (TypeDefinition $type): string {
            return $type->getFilename();
        }, $scanner->getDefinitions($scanner->getClasses()));

        $this->assertValues([TopClass::class, SubClass::class], $scanner->getClasses());
        $this->assertValues([
            TEST_FILES_DIRECTORY . '/TopClass.php',
            TEST_FILES_DIRECTORY . '/sub/SubClass.php',
        ], $paths);
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

    public function testIgnoreMissingClasses(): void
    {
        $class = $this->generateDynamicClass('MissingTest');

        $scanner = new Scanner();
        $scanner->parse("<?php class Foo Extends $class { }");
        $scanner->ignoreMissing();

        $this->assertValues(['Foo'], $scanner->getSubClasses($class));
    }

    public function testParsingErrorInString(): void
    {
        $scanner = new Scanner();

        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage('Error parsing:');
        $scanner->parse('<?php class foo {');
    }

    public function testParsingErrorInFile(): void
    {
        $scanner = new Scanner();

        $file = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($file, '<?php class foo {');

        $this->expectException(ParsingException::class);
        $this->expectExceptionMessage("Error parsing '$file':");

        try {
            $scanner->scanFile($file);
        } finally {
            unlink($file);
        }
    }

    public function testSkipSameActualFile()
    {
        $scanner = new Scanner();

        try {
            $file = tempnam(sys_get_temp_dir(), 'test');
            $link = tempnam(sys_get_temp_dir(), 'test');

            unlink($link);
            file_put_contents($file, '<?php class Foo { }');

            symlink($file, $link);

            $scanner->scanFile($file);
            $scanner->scanFile($link);

            $this->assertCount(1, $scanner->getDefinitions($scanner->getClasses()));
        } finally {
            if (isset($file) && file_exists($file)) {
                unlink($file);
            }

            if (isset($link) && file_exists($link)) {
                unlink($link);
            }
        }
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
