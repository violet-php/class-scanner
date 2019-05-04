<?php

namespace Violet\ClassScanner;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Violet\ClassScanner\Exception\FileNotFoundException;

/**
 * Scanner.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2019 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class Scanner
{
    public const T_CLASS = 1;
    public const T_ABSTRACT = 2;
    public const T_INTERFACE = 4;
    public const T_TRAIT = 8;
    public const T_ALL = self::T_CLASS | self::T_ABSTRACT | self::T_INTERFACE | self::T_TRAIT;

    private $parser;
    private $traverser;
    private $collector;
    private $ignore;
    private $autoload;
    private $files;

    public function __construct()
    {
        $this->ignore = false;
        $this->autoload = false;
        $this->collector = new ClassCollector();
        $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor($this->collector);
        $this->files = [];
    }

    public function allowAutoloading(bool $allow = true): self
    {
        $this->autoload = $allow;
        return $this;
    }

    public function ignoreMissing(bool $ignore = true): self
    {
        $this->ignore = $ignore;
        return $this;
    }

    public function getClasses($filter = self::T_ALL): array
    {
        $map = $this->collector->getMap();
        $types = $this->collector->getTypes();

        if ($filter === self::T_ALL) {
            return array_values(array_intersect_key($map, $types));
        }

        $classes = [];

        foreach ($types as $name => $type) {
            if ($type & $filter) {
                $classes[] = $map[$name];
            }
        }

        return $classes;
    }

    public function getSubClasses(string $class, int $filter = self::T_CLASS): array
    {
        if (! $this->ignore) {
            $this->collector->loadMissing($this->autoload);
        }

        $map = $this->collector->getMap();
        $children = $this->collector->getChildren();
        $types = $this->collector->getTypes();
        $traverse = array_flip($children[strtolower($class)] ?? []);
        $count = \count($traverse);
        $classes = [];

        for ($i = 0; $i < $count; $i++) {
            $name = key(\array_slice($traverse, $i, 1));

            if (isset($types[$name]) && $types[$name] & $filter) {
                $classes[] = $map[$name];
            }

            if (isset($children[$name])) {
                $traverse += array_flip($children[$name]);
                $count = \count($traverse);
            }
        }

        return $classes;
    }

    public function getFiles(array $classes): array
    {
        $classes = array_change_key_case(array_flip($classes), \CASE_LOWER);
        $files = array_values(array_intersect_key($this->files, $classes));

        if (! $files) {
            return [];
        }

        return array_keys(array_flip(array_merge(... $files)));
    }

    public function scanFile(string $filename): self
    {
        return $this->scan([$filename]);
    }

    public function scanDirectory(string $directory): self
    {
        return $this->scan(new \DirectoryIterator($directory));
    }

    public function scan(iterable $files): self
    {
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo) {
                $file = new \SplFileInfo($file);
            }

            if ($file->isFile()) {
                $classes = $this->parse(file_get_contents($file->getPathname()));

                foreach ($classes as $class) {
                    $this->files[strtolower($class)][] = $file->getPathname();
                }
            } elseif (! $file->isDir() && ! $file->isLink()) {
                throw new FileNotFoundException("The file path '$file' does not exist");
            }
        }

        return $this;
    }

    public function parse(string $code): array
    {
        $this->traverser->traverse($this->parser->parse($code));

        return $this->collector->getCollected();
    }
}
