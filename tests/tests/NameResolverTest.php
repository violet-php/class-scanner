<?php

namespace Violet\ClassScanner;

use PhpParser\ErrorHandler\Throwing;
use PhpParser\NameContext;
use PhpParser\Node\Name;
use PHPUnit\Framework\TestCase;
use Violet\ClassScanner\Exception\UnexpectedNodeException;

/**
 * NameResolverTest.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2019 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class NameResolverTest extends TestCase
{
    public function testInvalidNameNode(): void
    {
        $resolver = new NameResolver(new NameContext(new Throwing()));

        $this->expectException(UnexpectedNodeException::class);
        $resolver->resolveName(new Name('parent'));
    }
}
