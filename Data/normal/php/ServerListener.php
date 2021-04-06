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

namespace Symfony\Component\Panther;

use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;
use PHPUnit\Framework\TestSuite;

final class ServerListener implements TestListener
{
    use TestListenerDefaultImplementation;
    use ServerTrait;

    public function startTestSuite(TestSuite $suite): void
    {
        $this->keepServerOnTeardown();
    }

    public function endTestSuite(TestSuite $suite): void
    {
        $this->stopWebServer();
    }
}
