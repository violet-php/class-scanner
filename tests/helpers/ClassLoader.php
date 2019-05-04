<?php

namespace Violet\ClassScanner\Tests;

/**
 * ClassLoader.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2019 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class ClassLoader
{
    private $class;
    private $code;

    public function __construct(string $class, string $code)
    {
        $this->class = $class;
        $this->code = $code;
    }

    public function autoload(string $class): void
    {
        if ($class === $this->class) {
            eval($this->code);
        }
    }
}
