<?php
namespace Psalm\Tests;

use Psalm\Config;
use Psalm\Context;

class ThrowsInGlobalScopeTest extends TestCase
{
    /**
     * @return void
     */
    public function testUncaughtDocumentedThrowCall(): void
    {
        $this->expectException(\Psalm\Exception\CodeException::class);
        $this->expectExceptionMessage('UncaughtThrowInGlobalScope');
        Config::getInstance()->check_for_throws_in_global_scope = true;

        $this->addFile(
            'somefile.php',
            '<?php
                /**
                 * @throws RangeException
                 * @throws InvalidArgumentException
                 */
                function foo(int $x, int $y) : int {
                    if ($y === 0) {
                        throw new \RangeException("Cannot divide by zero");
                    }

                    if ($y < 0) {
                        throw new \InvalidArgumentException("This is also bad");
                    }

                    return intdiv($x, $y);
                }

                foo(0, 0);'
        );

        $context = new Context();

        $this->analyzeFile('somefile.php', $context);
    }

    /**
     * @return void
     */
    public function testCaughtDocumentedThrowCall(): void
    {
        Config::getInstance()->check_for_throws_docblock = true;
        Config::getInstance()->check_for_throws_in_global_scope = true;

        $this->addFile(
            'somefile.php',
            '<?php
                /**
                 * @throws RangeException
                 * @throws InvalidArgumentException
                 */
                function foo(int $x, int $y) : int {
                    if ($y === 0) {
                        throw new \RangeException("Cannot divide by zero");
                    }

                    if ($y < 0) {
                        throw new \InvalidArgumentException("This is also bad");
                    }

                    return intdiv($x, $y);
                }

                try {
                    foo(0, 0);
                } catch (Exception $e) {}'
        );

        $context = new Context();

        $this->analyzeFile('somefile.php', $context);
    }

    /**
     * @return void
     */
    public function testUncaughtUndocumentedThrowCall(): void
    {
        Config::getInstance()->check_for_throws_in_global_scope = true;

        $this->addFile(
            'somefile.php',
            '<?php
                function foo(int $x, int $y) : int {
                    if ($y === 0) {
                        throw new \RangeException("Cannot divide by zero");
                    }

                    if ($y < 0) {
                        throw new \InvalidArgumentException("This is also bad");
                    }

                    return intdiv($x, $y);
                }

                foo(0, 0);'
        );

        $context = new Context();

        $this->analyzeFile('somefile.php', $context);
    }

    /**
     * @return void
     */
    public function testUncaughtDocumentedThrowCallInNamespace(): void
    {
        $this->expectException(\Psalm\Exception\CodeException::class);
        $this->expectExceptionMessage('UncaughtThrowInGlobalScope');
        Config::getInstance()->check_for_throws_in_global_scope = true;

        $this->addFile(
            'somefile.php',
            '<?php
                namespace ns;
                /**
                 * @throws \RangeException
                 * @throws \InvalidArgumentException
                 */
                function foo(int $x, int $y) : int {
                    if ($y === 0) {
                        throw new \RangeException("Cannot divide by zero");
                    }

                    if ($y < 0) {
                        throw new \InvalidArgumentException("This is also bad");
                    }

                    return intdiv($x, $y);
                }

                foo(0, 0);'
        );

        $context = new Context();

        $this->analyzeFile('somefile.php', $context);
    }

    /**
     * @return void
     */
    public function testUncaughtThrow(): void
    {
        $this->expectException(\Psalm\Exception\CodeException::class);
        $this->expectExceptionMessage('UncaughtThrowInGlobalScope');

        Config::getInstance()->check_for_throws_in_global_scope = true;

        $this->addFile(
            'somefile.php',
            '<?php
                throw new \Exception();'
        );

        $context = new Context();

        $this->analyzeFile('somefile.php', $context);
    }

    /**
     * @return void
     */
    public function testCaughtThrow(): void
    {
        Config::getInstance()->check_for_throws_in_global_scope = true;

        $this->addFile(
            'somefile.php',
            '<?php
                try {
                    throw new \Exception();
                } catch (\Exception $e) {}'
        );

        $context = new Context();

        $this->analyzeFile('somefile.php', $context);
    }

    /**
     * @return void
     */
    public function testUncaughtThrowWhenSuppressing(): void
    {
        Config::getInstance()->check_for_throws_in_global_scope = true;

        $this->addFile(
            'somefile.php',
            '<?php
                /** @psalm-suppress UncaughtThrowInGlobalScope */
                throw new \Exception();'
        );

        $context = new Context();

        $this->analyzeFile('somefile.php', $context, false);
    }

    /**
     * @return void
     */
    public function testUncaughtThrowInNamespaceWhenSuppressing(): void
    {
        Config::getInstance()->check_for_throws_in_global_scope = true;

        $this->addFile(
            'somefile.php',
            '<?php
                namespace ns;
                /** @psalm-suppress UncaughtThrowInGlobalScope */
                throw new \Exception();'
        );

        $context = new Context();

        $this->analyzeFile('somefile.php', $context, false);
    }

    /**
     * @return void
     */
    public function testUncaughtDocumentedThrowCallWhenSuppressing(): void
    {
        Config::getInstance()->check_for_throws_in_global_scope = true;

        $this->addFile(
            'somefile.php',
            '<?php
                /**
                 * @throws RangeException
                 * @throws InvalidArgumentException
                 */
                function foo(int $x, int $y) : int {
                    if ($y === 0) {
                        throw new \RangeException("Cannot divide by zero");
                    }

                    if ($y < 0) {
                        throw new \InvalidArgumentException("This is also bad");
                    }

                    return intdiv($x, $y);
                }

                /** @psalm-suppress UncaughtThrowInGlobalScope */
                foo(0, 0);'
        );

        $context = new Context();

        $this->analyzeFile('somefile.php', $context, false);
    }

    /**
     * @return void
     */
    public function testUncaughtDocumentedThrowCallInNamespaceWhenSuppressing(): void
    {
        Config::getInstance()->check_for_throws_in_global_scope = true;

        $this->addFile(
            'somefile.php',
            '<?php
                namespace ns;
                /**
                 * @throws \RangeException
                 * @throws \InvalidArgumentException
                 */
                function foo(int $x, int $y) : int {
                    if ($y === 0) {
                        throw new \RangeException("Cannot divide by zero");
                    }

                    if ($y < 0) {
                        throw new \InvalidArgumentException("This is also bad");
                    }

                    return intdiv($x, $y);
                }

                /** @psalm-suppress UncaughtThrowInGlobalScope */
                foo(0, 0);'
        );

        $context = new Context();

        $this->analyzeFile('somefile.php', $context, false);
    }

    /**
     * @return void
     */
    public function testUncaughtDocumentedThrowCallWhenSuppressingFirst(): void
    {
        $this->expectExceptionMessage('UncaughtThrowInGlobalScope');
        $this->expectException(\Psalm\Exception\CodeException::class);
        Config::getInstance()->check_for_throws_in_global_scope = true;

        $this->addFile(
            'somefile.php',
            '<?php
                /**
                 * @throws RangeException
                 * @throws InvalidArgumentException
                 */
                function foo(int $x, int $y) : int {
                    if ($y === 0) {
                        throw new \RangeException("Cannot divide by zero");
                    }

                    if ($y < 0) {
                        throw new \InvalidArgumentException("This is also bad");
                    }

                    return intdiv($x, $y);
                }

                /** @psalm-suppress UncaughtThrowInGlobalScope */
                foo(0, 0);

                foo(0, 0);'
        );

        $context = new Context();

        $this->analyzeFile('somefile.php', $context);
    }
}
