<?php

declare(strict_types=1);

namespace IanFoxDev\PayoutSplit\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The example prints the same thing on every run: a change in the numbers is a change
 * in the library.
 */
final class ExampleTest extends TestCase
{
    public function testStudiosExampleOutputIsStable(): void
    {
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../examples/studios/run.php'));
        self::assertIsString($output);
        self::assertStringEqualsFile(__DIR__ . '/../examples/studios/expected-output.txt', $output);
    }
}
