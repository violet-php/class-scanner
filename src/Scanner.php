<?php

namespace Violet\ClassScanner;

use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Violet\ClassScanner\Exception\FileNotFoundException;
use Violet\ClassScanner\Exception\ParsingException;

/**
 * Scanner.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2019 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class Scanner
{
    /** @var Parser */
    private $parser;

    /** @var NodeTraverser */
    private $traverser;

    /** @var ClassCollector */
    private $collector;

    /** @var bool */
    private $ignore;

    /** @var bool */
    private $autoload;

    /** @var bool[] */
    private $scannedFiles;

    public function __construct()
    {
        $this->ignore = false;
        $this->autoload = false;
        $this->collector = new ClassCollector();
        $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor($this->collector);
        $this->scannedFiles = [];
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

    public function getClasses(int $filter = TypeDefinition::TYPE_ANY): array
    {
        $map = $this->collector->getMap();
        $types = $this->collector->getTypes();

        if ($filter === TypeDefinition::TYPE_ANY) {
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

    public function getSubClasses(string $class, int $filter = TypeDefinition::TYPE_CLASS): array
    {
        $this->collector->loadMissing($this->autoload, $this->ignore);

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

    /**
     * @param string[] $classes
     * @return TypeDefinition[]
     */
    public function getDefinitions(array $classes): array
    {
        $definitions = $this->collector->getDefinitions();
        $results = [];

        foreach ($classes as $class) {
            $class = strtolower($class);

            if (isset($definitions[$class])) {
                array_push($results, ... $definitions[$class]);
            }
        }

        return $results;
    }

    public function scanFile(string $filename): self
    {
        return $this->scan([$filename]);
    }

    public function scanDirectory(string $directory): self
    {
        return $this->scan(new \DirectoryIterator($directory));
    }

    /**
     * @param iterable<string|\SplFileInfo> $files
     * @return Scanner
     * @throws FileNotFoundException
     * @throws ParsingException
     */
    public function scan(iterable $files): self
    {
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo) {
                $file = new \SplFileInfo((string) $file);
            }

            if ($file->isFile()) {
                $real = $file->getRealPath();

                if (isset($this->scannedFiles[$real])) {
                    continue;
                }

                $this->collector->setCurrentFile($real);

                try {
                    $this->parse(file_get_contents($real));
                    $this->scannedFiles[$real] = true;
                } finally {
                    $this->collector->setCurrentFile(null);
                }
            } elseif (! $file->isDir() && ! $file->isLink()) {
                throw new FileNotFoundException("The file path '$file' does not exist");
            }
        }

        return $this;
    }

    /**
     * @param string $code
     * @return TypeDefinition[]
     * @throws ParsingException
     */
    public function parse(string $code): array
    {
        try {
            $ast = $this->parser->parse($code);
        } catch (\Exception $exception) {
            $currentFile = $this->collector->getCurrentFile();
            $message = $currentFile === null
                ? sprintf('Error parsing: %s', $exception->getMessage())
                : sprintf("Error parsing '%s': %s", $currentFile, $exception->getMessage());
            throw new ParsingException($message, 0, $exception);
        }

        $this->traverser->traverse($ast);

        return $this->collector->getCollected();
    }
}
