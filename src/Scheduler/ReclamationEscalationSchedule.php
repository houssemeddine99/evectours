<?php

namespace App\Scheduler;

use App\Message\EscalateReclamationsMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Runs reclamation SLA escalation every hour via Symfony Scheduler.
 * Start the worker: php bin/console messenger:consume scheduler_default --time-limit=3600
 */
#[AsSchedule('default')]
final class ReclamationEscalationSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(
            RecurringMessage::cron('0 * * * *', new EscalateReclamationsMessage())
        );
    }
}
