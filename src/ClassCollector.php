<?php

namespace Violet\ClassScanner;

use PhpParser\ErrorHandler\Throwing;
use PhpParser\NameContext;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use Violet\ClassScanner\Exception\UndefinedClassException;

/**
 * ClassCollector.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2019 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class ClassCollector extends NodeVisitorAbstract
{
    /** @var NameContext */
    private $context;

    /** @var string[] */
    private $map;

    /** @var string[] */
    private $parentNames;

    /** @var array[] */
    private $children;

    /** @var int[] */
    private $types;

    /** @var string[] */
    private $collected;

    public function __construct()
    {
        $this->context = new NameContext(new Throwing());
        $this->map = [];
        $this->children = [];
        $this->parentNames = [];
        $this->types = [];
        $this->collected = [];
    }

    public function getMap(): array
    {
        return $this->map;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function getTypes(): array
    {
        return $this->types;
    }

    public function getCollected(): array
    {
        return $this->collected;
    }

    public function loadMissing(bool $autoload): void
    {
        $missing = array_diff_key($this->parentNames, $this->map);
        $parents = [];

        foreach ($missing as $name) {
            if (!$this->definitionExists($name, $autoload)) {
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

    public function beforeTraverse(array $nodes)
    {
        $this->context->startNamespace();
        $this->collected = [];
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->context->startNamespace($node->name);
        } elseif ($node instanceof Node\Stmt\Use_) {
            return $this->addAliases($node->uses, $node->type, '');
        } elseif ($node instanceof Node\Stmt\GroupUse) {
            return $this->addAliases($node->uses, $node->type, $node->prefix->toString());
        } elseif ($node instanceof Node\Stmt\Class_) {
            if ($node->name === null) {
                return NodeTraverser::DONT_TRAVERSE_CHILDREN;
            }

            $parents = array_merge(
                array_values($node->implements),
                $node->extends ? [$node->extends] : [],
                $this->getTraits($node->stmts)
            );

            return $this->collect($node, $node->isAbstract() ? Scanner::T_ABSTRACT : Scanner::T_CLASS, $parents);
        } elseif ($node instanceof Node\Stmt\Interface_) {
            return $this->collect($node, Scanner::T_INTERFACE, $node->extends);
        } elseif ($node instanceof Node\Stmt\Trait_) {
            return $this->collect($node, Scanner::T_TRAIT, $this->getTraits($node->stmts));
        }
    }

    /**
     * @param Node\Stmt\UseUse[] $uses
     * @param int $type
     * @param string $prefix
     * @return int
     */
    private function addAliases(array $uses, int $type, string $prefix): int
    {
        foreach ($uses as $use) {
            $this->context->addAlias(
                $prefix ? Node\Name::concat($prefix, $use->name) : $use->name,
                $use->getAlias()->toString(),
                $use->type | $type,
                $use->getAttributes()
            );
        }

        return NodeTraverser::DONT_TRAVERSE_CHILDREN;
    }

    /**
     * @param Node\Stmt[] $statements
     * @return Node\Name[]
     */
    private function getTraits(array $statements): array
    {
        $traits = [];

        foreach ($statements as $statement) {
            if ($statement instanceof Node\Stmt\TraitUse && $statement->traits) {
                array_push($traits, ... array_values($statement->traits));
            }
        }

        return $traits;
    }

    /**
     * @param Node\Stmt\ClassLike $node
     * @param int $type
     * @param Node\Name[] $parents
     * @return int
     */
    private function collect(Node\Stmt\ClassLike $node, int $type, array $parents): int
    {
        $name = $this->resolveName($node->name)->toString();
        $lower = strtolower($name);

        if (!isset($this->types[$lower])) {
            $this->types[$lower] = 0;
        }

        $this->collected[] = $name;
        $this->map[$lower] = $name;
        $this->types[$lower] |= $type;

        foreach ($parents as $parent) {
            $parentName = $this->resolveName($parent);
            $lowerParent = $parentName->toLowerString();

            $this->children[$lowerParent][] = $lower;

            if (!isset($this->map[$lowerParent], $this->parentNames[$lowerParent])) {
                $this->parentNames[$lowerParent] = $parentName->toString();
            }
        }

        return NodeTraverser::DONT_TRAVERSE_CHILDREN;
    }

    /**
     * @param Node\Identifier|Node\Name $name
     * @return Node\Name\FullyQualified
     */
    private function resolveName($name): Node\Name\FullyQualified
    {
        if ($name instanceof Node\Identifier) {
            return Node\Name\FullyQualified::concat($this->context->getNamespace(), $name->toString());
        }

        return $this->context->getResolvedClassName($name);
    }
}
