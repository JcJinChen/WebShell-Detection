<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Scheduler\Schedule;

class SpecificTime extends Schedule
{
    /**
     * @var int
     */
    private $scheduledTime;

    public function __construct($scheduledTime)
    {
        $this->scheduledTime = $scheduledTime;
    }

    public function getRescheduledTime()
    {
        return $this->scheduledTime;
    }

    public function setDay($_day)
    {
        throw new \Exception('not supported');
    }
}