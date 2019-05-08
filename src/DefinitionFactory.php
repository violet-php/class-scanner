<?php

namespace Violet\ClassScanner;

use PhpParser\Node;
use Violet\ClassScanner\Exception\UnexpectedNodeException;

/**
 * Factory for creating type definitions from ClassLike nodes.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2019 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class DefinitionFactory
{
    /** @var NameResolver The resolver used to resolve fully qualified names of types */
    private $resolver;

    /**
     * DefinitionFactory constructor.
     * @param NameResolver $resolver Resolver used to resolve fully qualified names for types
     */
    public function __construct(NameResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Creates a new type definition bu inspecting the given ClassLike node.
     * @param Node\Stmt\ClassLike $node The node to inspect for creating a a type definition
     * @return TypeDefinition The type definition created from the provided node
     * @throws UnexpectedNodeException If an unsupported ClassLike node is provided
     */
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

    /**
     * Creates a class type definition from the provided node.
     * @param Node\Stmt\Class_ $node The node to inspect
     * @return TypeDefinition Type definition from the inspected node
     * @throws UnexpectedNodeException If the provided node is for anonymous class
     */
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

    /**
     * Creates an interface type definition from the provided node.
     * @param Node\Stmt\Interface_ $node The node to inspect
     * @return TypeDefinition Type definition from the inspected node
     */
    private function createInterface(Node\Stmt\Interface_ $node): TypeDefinition
    {
        return (new TypeDefinition($this->resolver->resolveIdentifier($node->name), TypeDefinition::TYPE_INTERFACE))
            ->withInterfaces(... $this->resolver->resolveNames($node->extends));
    }

    /**
     * Creates a trait type definition from the provided node.
     * @param Node\Stmt\Trait_ $node The node to inspect
     * @return TypeDefinition Type definition from the inspected node
     */
    private function createTrait(Node\Stmt\Trait_ $node): TypeDefinition
    {
        return (new TypeDefinition($this->resolver->resolveIdentifier($node->name), TypeDefinition::TYPE_TRAIT))
            ->withTraits(... $this->resolver->resolveNames($this->getTraits($node->stmts)));
    }

    /**
     * Returns the name of the used traits from the list of statements.
     * @param Node\Stmt[] $statements List of statements for a class or a trait
     * @return Node\Name[] Names of all the used traits
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
