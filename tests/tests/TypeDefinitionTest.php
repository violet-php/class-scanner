<?php

namespace Violet\ClassScanner;

use PHPUnit\Framework\TestCase;
use Violet\ClassScanner\Exception\UnexpectedNodeException;

/**
 * TypeDefinitionTest.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2019 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class TypeDefinitionTest extends TestCase
{
    public function testInvalidType(): void
    {
        $this->expectException(UnexpectedNodeException::class);
        new TypeDefinition('TypeName', 0);
    }
}
