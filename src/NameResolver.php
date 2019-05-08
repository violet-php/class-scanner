<?php

namespace Violet\ClassScanner;

use PhpParser\ErrorHandler\Throwing;
use PhpParser\NameContext;
use PhpParser\Node;
use Violet\ClassScanner\Exception\UnexpectedNodeException;

/**
 * Class for resolving fully qualified names of ClassLike nodes.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2019 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class NameResolver
{
    /** @var NameContext The context used for name resolution */
    private $context;

    /**
     * NameResolver constructor.
     */
    public function __construct()
    {
        $this->context = new NameContext(new Throwing());
    }

    /**
     * Initializes the name context in the beginning of parsing a file.
     */
    public function initialize(): void
    {
        $this->context->startNamespace();
    }

    /**
     * Sets the current namespace for the name context.
     * @param Node\Stmt\Namespace_ $node The namespace node for the current namespace
     */
    public function setNamespace(Node\Stmt\Namespace_ $node): void
    {
        $this->context->startNamespace($node->name);
    }

    /**
     * Adds an alias via use statement to the current namespace for the name context.
     * @param Node\Stmt\Use_ $node The use statement node to process for the alias
     */
    public function addUseStatement(Node\Stmt\Use_ $node): void
    {
        $this->addAliases($node->uses, $node->type, '');
    }

    /**
     * Adds aliases via a group use statement to the current namespace for the name context.
     * @param Node\Stmt\GroupUse $node The group use statement node to process for the aliases
     */
    public function addGroupUseStatement(Node\Stmt\GroupUse $node): void
    {
        $this->addAliases($node->uses, $node->type, $node->prefix->toString());
    }

    /**
     * Adds aliases to the current name context.
     * @param Node\Stmt\UseUse[] $uses The uses from use or group use statement
     * @param int $type The type of the alias from the use node
     * @param string $prefix The prefix for the group use statement
     */
    private function addAliases(array $uses, int $type, string $prefix): void
    {
        foreach ($uses as $use) {
            $this->context->addAlias(
                $prefix ? Node\Name::concat($prefix, $use->name) : $use->name,
                $use->getAlias()->toString(),
                $use->type | $type,
                $use->getAttributes()
            );
        }
    }

    /**
     * Resolves the fully qualified name for the type based on the current name context.
     * @param Node\Identifier $node The identifier of a defined type
     * @return string The fully qualified name for the type
     */
    public function resolveIdentifier(Node\Identifier $node): string
    {
        return (string) Node\Name\FullyQualified::concat($this->context->getNamespace(), (string) $node);
    }

    /**
     * Resolves the fully qualified names fo the referenced types based on the current name context.
     * @param Node\Name[] $nodes The name nodes to resolve
     * @return string[] The resolved fully qualified names
     * @throws UnexpectedNodeException If a special relative class name is provided
     */
    public function resolveNames(iterable $nodes): array
    {
        $names = [];

        foreach ($nodes as $node) {
            $names[] = $this->resolveName($node);
        }

        return $names;
    }

    /**
     * Resolves the fully qualified name of the referenced type based on the current name context.
     * @param Node\Name $node The name node to resolve
     * @return string The resolved fully qualified name
     * @throws UnexpectedNodeException If a special relative class name is provided
     */
    public function resolveName(Node\Name $node): string
    {
        $name = $this->context->getResolvedClassName($node);

        if (! $name instanceof Node\Name\FullyQualified) {
            throw new UnexpectedNodeException('Only names that can be fully qualified can be resolved');
        }

        return (string) $name;
    }
}
