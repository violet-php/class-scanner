<?php

namespace Violet\ClassScanner;

use Violet\ClassScanner\Exception\UnexpectedNodeException;

/**
 * An immutable representation of a class, interface or trait definition in source code.
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

    /** @var string The fully qualified named of the type */
    private $name;

    /** @var int The actual type of the definition */
    private $type;

    /** @var string|null The parent class the this type extends or null if none */
    private $parent;

    /** @var string[] List of interfaces this type implements or extends */
    private $interfaces;

    /** @var string[] List of traits used by this type*/
    private $traits;

    /** @var string|null The name of the file where the type is defined or null if not provided */
    private $filename;

    /**
     * TypeDefinition constructor.
     * @param string $name The fully qualified name of the type
     * @param int $type The type of the definition represented by one of the type constants
     * @throws UnexpectedNodeException If the type does not match any of the type constants
     */
    public function __construct(string $name, int $type)
    {
        if (!\in_array($type, [self::TYPE_CLASS, self::TYPE_ABSTRACT, self::TYPE_INTERFACE, self::TYPE_TRAIT], true)) {
            throw new UnexpectedNodeException('Invalid class definition type');
        }

        $this->name = $name;
        $this->type = $type;
        $this->interfaces = [];
        $this->traits = [];
    }

    /**
     * Returns a new type definition with the given parent.
     * @param string|null $parent The fully qualified named of the parent class that this class extends
     * @return TypeDefinition A new type definition with the given parent
     */
    public function withParent(?string $parent): self
    {
        if ($parent === $this->parent) {
            return $this;
        }

        $clone = clone $this;
        $clone->parent = $parent;

        return $clone;
    }

    /**
     * Returns a new type definition with the given interfaces.
     * @param string ...$interfaces The fully qualified names of the implemented or extended interfaces
     * @return TypeDefinition A new type definition with the given interfaces
     */
    public function withInterfaces(string ... $interfaces): self
    {
        if ($interfaces === $this->interfaces) {
            return $this;
        }

        $clone = clone $this;
        $clone->interfaces = $interfaces;

        return $clone;
    }

    /**
     * Returns a new type definition with the given traits.
     * @param string ...$traits The fully qualified names of the traits used by this type
     * @return TypeDefinition A new type definition with the given traits
     */
    public function withTraits(string ... $traits): self
    {
        if ($traits === $this->traits) {
            return $this;
        }

        $clone = clone $this;
        $clone->traits = $traits;

        return $clone;
    }

    /**
     * Returns a new type definition with the given filename
     * @param string|null $filename Path to the file where this type is defined
     * @return TypeDefinition A new type definition with the given filename
     */
    public function withFilename(?string $filename): self
    {
        if ($filename === $this->filename) {
            return $this;
        }

        $clone = clone $this;
        $clone->filename = $filename;

        return $clone;
    }

    /**
     * Returns fully qualified named of the type.
     * @return string Fully qualified named of the type
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the type of this definition represented by one of the type constants
     * @return int Type of the definition that equals one of the type constants
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * Returns the fully qualified named of the parent class this type extends.
     * @return string|null Fully qualified named of the parent class or null if none
     */
    public function getParentName(): ?string
    {
        return $this->parent;
    }

    /**
     * Returns the fully qualified names of the interfaces implemented or extended by this type.
     * @return string[] Fully qualified names of the implemented or extended interfaces
     */
    public function getInterfaceNames(): array
    {
        return $this->interfaces;
    }

    /**
     * Returns the fully qualified names of the traits used by this type.
     * @return string[] Fully qualified names of the traits implemented by this type
     */
    public function getTraitNames(): array
    {
        return $this->traits;
    }

    /**
     * Returns list of fully qualified names of the parent class, interfaces and traits.
     * @return string[] Fully qualified names of the parent, interfaces and traits
     */
    public function getAllNames(): array
    {
        return array_merge(
            $this->parent ? [$this->parent] : [],
            $this->interfaces,
            $this->traits
        );
    }

    /**
     * Returns the the path to the file of the definition or null if none.
     * @return string|null Path to the definition of the file or null if none
     */
    public function getFilename(): ?string
    {
        return $this->filename;
    }
}
