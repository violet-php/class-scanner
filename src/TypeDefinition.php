<?php

namespace Violet\ClassScanner;

use Violet\ClassScanner\Exception\UnexpectedNodeException;

/**
 * ClassDefinition.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2019 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class TypeDefinition
{
    public const TYPE_CLASS = 1;
    public const TYPE_ABSTRACT = 2;
    public const TYPE_INTERFACE = 4;
    public const TYPE_TRAIT = 8;
    public const TYPE_ANY = self::TYPE_CLASS | self::TYPE_ABSTRACT | self::TYPE_INTERFACE | self::TYPE_TRAIT;

    private $name;
    private $type;
    private $parent;
    private $interfaces;
    private $traits;
    private $filename;

    public function __construct(string $name, int $type)
    {
        if (!in_array($type, [self::TYPE_CLASS, self::TYPE_ABSTRACT, self::TYPE_INTERFACE, self::TYPE_TRAIT], true)) {
            throw new UnexpectedNodeException('Invalid class definition type');
        }

        $this->name = $name;
        $this->type = $type;
        $this->interfaces = [];
        $this->traits = [];
    }

    public function withParent(?string $parent): self
    {
        if ($parent === $this->parent) {
            return $this;
        }

        $clone = clone $this;
        $clone->parent = $parent;

        return $clone;
    }

    public function withInterfaces(string ... $interfaces): self
    {
        if ($interfaces === $this->interfaces) {
            return $this;
        }

        $clone = clone $this;
        $clone->interfaces = $interfaces;

        return $clone;
    }

    public function withTraits(string ... $traits): self
    {
        if ($traits === $this->traits) {
            return $this;
        }

        $clone = clone $this;
        $clone->traits = $traits;

        return $clone;
    }

    public function withFilename(?string $filename): self
    {
        if ($filename === $this->filename) {
            return $this;
        }

        $clone = clone $this;
        $clone->filename = $filename;

        return $clone;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getParentName(): ?string
    {
        return $this->parent;
    }

    public function getInterfaceNames(): array
    {
        return $this->interfaces;
    }

    public function getTraitNames(): array
    {
        return $this->traits;
    }

    public function getAllNames(): array
    {
        return array_merge(
            $this->parent ? [$this->parent] : [],
            $this->interfaces,
            $this->traits,
        );
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }
}
