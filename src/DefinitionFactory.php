<?php

namespace Violet\ClassScanner;

use PhpParser\Node;
use Violet\ClassScanner\Exception\UnexpectedNodeException;

/**
 * DefinitionFactory.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2019 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class DefinitionFactory
{
    /** @var NameResolver */
    private $resolver;

    public function __construct(NameResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    public function createFromNode(Node\Stmt\ClassLike $node): TypeDefinition
    {
        switch (true) {
            case $node instanceof Node\Stmt\Class_:
                return $this->createClass($node);
            case $node instanceof Node\Stmt\Interface_:
                return $this->createInterface($node);
            case $node instanceof Node\Stmt\Trait_:
                return $this->createTrait($node);
            default:
                throw new UnexpectedNodeException('Unexpected node for class definition: ' . \get_class($node));
        }
    }

    private function createClass(Node\Stmt\Class_ $node): TypeDefinition
    {
        $name = $node->name;
        $type = $node->isAbstract() ? TypeDefinition::TYPE_ABSTRACT : TypeDefinition::TYPE_CLASS;

        if ($name === null) {
            throw new UnexpectedNodeException('Definitions for anonymous classes are not supported');
        }

        return (new TypeDefinition($this->resolver->resolveIdentifier($name), $type))
            ->withParent($node->extends ? $this->resolver->resolveName($node->extends) : null)
            ->withInterfaces(... $this->resolver->resolveNames($node->implements))
            ->withTraits(... $this->resolver->resolveNames($this->getTraits($node->stmts)));
    }

    private function createInterface(Node\Stmt\Interface_ $node): TypeDefinition
    {
        return (new TypeDefinition($this->resolver->resolveIdentifier($node->name), TypeDefinition::TYPE_INTERFACE))
            ->withInterfaces(... $this->resolver->resolveNames($node->extends));
    }

    private function createTrait(Node\Stmt\Trait_ $node): TypeDefinition
    {
        return (new TypeDefinition($this->resolver->resolveIdentifier($node->name), TypeDefinition::TYPE_TRAIT))
            ->withTraits(... $this->resolver->resolveNames($this->getTraits($node->stmts)));
    }

    /**
     * @param Node\Stmt[] $statements
     * @return Node\Name[]
     */
    private function getTraits(array $statements): array
    {
        $traits = [];

        foreach ($statements as $statement) {
            if ($statement instanceof Node\Stmt\TraitUse) {
                array_push($traits, ... $statement->traits);
            }
        }

        return $traits;
    }
}
