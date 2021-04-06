<?php

/*
 * This file is part of the Panther project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Panther\Tests;

use PHPUnit\Framework\TestSuite;
use Symfony\Component\Panther\PantherTestCase;
use Symfony\Component\Panther\ServerListener;

class ServerListenerTest extends TestCase
{
    public static function tearDownAfterClass()
    {
        PantherTestCase::$stopServerOnTeardown = true;
    }

    public function testStartAndStop(): void
    {
        $testSuite = new TestSuite();
        $listener = new ServerListener();

        $listener->startTestSuite($testSuite);
        static::assertFalse(PantherTestCase::$stopServerOnTeardown);

        $listener->endTestSuite($testSuite);
        static::assertNull(PantherTestCase::$webServerManager);
    }
}
