<?php

namespace Violet\ClassScanner;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use Violet\ClassScanner\Exception\UndefinedClassException;

/**
 * A node visitor for collecting class, interface and trait declarations from files
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2019 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class ClassCollector extends NodeVisitorAbstract
{
    /** @var NameResolver The name resolver for resolving fully qualified names of types */
    private $resolver;

    /** @var DefinitionFactory The factory used to create type definitions from nodes */
    private $factory;

    /** @var array<array<TypeDefinition>> List of definitions for each name */
    private $definitions;

    /** @var string[] Case sensitive names for each type */
    private $map;

    /** @var string[] List of potentially unresolved names of parent types */
    private $parentNames;

    /** @var array<array<string>> Child types for each parent type */
    private $children;

    /** @var int[] Types of each name */
    private $types;

    /** @var TypeDefinition[] List of type definitions collection from current traversal */
    private $collected;

    /** @var string|null Path to the file that we are currently traversing or null for none */
    private $currentFile;

    public function __construct()
    {
        $this->resolver = new NameResolver();
        $this->factory = new DefinitionFactory($this->resolver);
        $this->map = [];
        $this->children = [];
        $this->parentNames = [];
        $this->types = [];
        $this->collected = [];
        $this->definitions = [];
    }

    /**
     * All type definitions for each lower cased name.
     * @return array<array<TypeDefinition>> Type definitions per each lower cased name
     */
    public function getDefinitions(): array
    {
        return $this->definitions;
    }

    /**
     *
     * @return string[]
     */
    public function getMap(): array
    {
        return $this->map;
    }

    /**
     * @return array[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * @return int[]
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * @return TypeDefinition[]
     */
    public function getCollected(): array
    {
        return $this->collected;
    }

    public function loadMissing(bool $autoload, bool $ignoreMissing): void
    {
        $missing = array_diff_key($this->parentNames, $this->map);
        $parents = [];

        foreach ($missing as $name) {
            if (!$this->definitionExists($name, $autoload)) {
                if ($ignoreMissing) {
                    continue;
                }

                throw new UndefinedClassException("Could not find definition for '$name'");
            }

            $classes = $this->loadParentReflections(new \ReflectionClass($name));

            if ($classes) {
                array_push($parents, ... $classes);
            }
        }

        while ($parents) {
            $classes = $this->loadParentReflections(array_pop($parents));

            if ($classes) {
                array_push($parents, ... $classes);
            }
        }

        $this->parentNames = [];
    }

    private function definitionExists(string $name, bool $autoload): bool
    {
        return class_exists($name, $autoload) || interface_exists($name, false) || trait_exists($name, false);
    }

    /**
     * @param \ReflectionClass $child
     * @return \ReflectionClass[]
     */
    private function loadParentReflections(\ReflectionClass $child): array
    {
        $parents = [];
        $newParents = [];

        foreach ($child->getInterfaces() as $interface) {
            $parents[] = $interface;
        }

        foreach ($child->getTraits() as $trait) {
            $parents[] = $trait;
        }

        $parent = $child->getParentClass();

        if ($parent) {
            $parents[] = $parent;
        }

        $name = $child->getName();
        $lower = strtolower($name);
        $this->map[$lower] = $name;

        foreach ($parents as $parent) {
            $lowerParent = strtolower($parent->getName());
            $this->children[$lowerParent][] = $lower;

            if (!isset($this->map[$lowerParent])) {
                $newParents[] = $parent;
            }
        }

        return $newParents;
    }

    public function setCurrentFile(?string $filename): void
    {
        $this->currentFile = $filename;
    }

    public function getCurrentFile(): ?string
    {
        return $this->currentFile;
    }

    public function beforeTraverse(array $nodes)
    {
        $this->resolver->initialize();
        $this->collected = [];
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->resolver->setNamespace($node);
        } elseif ($node instanceof Node\Stmt\Use_) {
            $this->resolver->addUseStatement($node);
        } elseif ($node instanceof Node\Stmt\GroupUse) {
            $this->resolver->addGroupUseStatement($node);
        } elseif ($node instanceof Node\Stmt\ClassLike) {
            if ($node->name !== null) {
                $this->collect($this->factory->createFromNode($node));
            }

            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }
    }

    private function collect(TypeDefinition $definition): int
    {
        $definition = $definition->withFilename($this->currentFile);
        $lower = strtolower($definition->getName());

        if (!isset($this->types[$lower])) {
            $this->types[$lower] = 0;
        }

        $this->collected[] = $definition;
        $this->definitions[$lower][] = $definition;
        $this->map[$lower] = $definition->getName();
        $this->types[$lower] |= $definition->getType();

        foreach ($definition->getAllNames() as $parent) {
            $lowerParent = strtolower($parent);

            $this->children[$lowerParent][] = $lower;

            if (!isset($this->map[$lowerParent], $this->parentNames[$lowerParent])) {
                $this->parentNames[$lowerParent] = $parent;
            }
        }

        return NodeTraverser::DONT_TRAVERSE_CHILDREN;
    }
}
