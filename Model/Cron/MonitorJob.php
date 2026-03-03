<?php
declare(strict_types=1);

namespace Blackbird\IndexMonitoring\Model\Cron;

use Blackbird\IndexMonitoring\Service\MonitorService;

class MonitorJob
{
    public function __construct(
        private readonly MonitorService $monitorService
    ) {
    }

    public function execute(): void
    {
        $this->monitorService->execute();
    }
}
