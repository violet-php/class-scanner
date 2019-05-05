<?php

namespace Violet\ClassScanner;

use PhpParser\NameContext;
use PhpParser\Node;
use Violet\ClassScanner\Exception\UnexpectedNodeException;

/**
 * NameResolver.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2019 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class NameResolver
{
    private $context;

    public function __construct(NameContext $context)
    {
        $this->context = $context;
    }

    public function initialize(): void
    {
        $this->context->startNamespace();
    }

    public function setNamespace(Node\Stmt\Namespace_ $node): void
    {
        $this->context->startNamespace($node->name);
    }

    public function addUseStatement(Node\Stmt\Use_ $node): void
    {
        $this->addAliases($node->uses, $node->type, '');
    }

    public function addGroupUseStatement(Node\Stmt\GroupUse $node): void
    {
        $this->addAliases($node->uses, $node->type, $node->prefix->toString());
    }

    /**
     * @param Node\Stmt\UseUse[] $uses
     * @param int $type
     * @param string $prefix
     * @return int
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

    public function resolveIdentifier(Node\Identifier $node): string
    {
        return (string) Node\Name\FullyQualified::concat($this->context->getNamespace(), (string) $node);
    }

    public function resolveNames(iterable $nodes): array
    {
        $names = [];

        foreach ($nodes as $node) {
            $names[] = $this->resolveName($node);
        }

        return $names;
    }

    public function resolveName(Node\Name $node): string
    {
        $name = $this->context->getResolvedClassName($node);

        if (! $name instanceof Node\Name\FullyQualified) {
            throw new UnexpectedNodeException('Only names that can be fully qualified can be resolved');
        }

        return (string) $name;
    }

}
