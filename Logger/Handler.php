<?php
declare(strict_types=1);

namespace Blackbird\IndexMonitoring\Logger;

use Monolog\Handler\StreamHandler;
use Monolog\Logger as MonologLogger;

class Handler extends StreamHandler
{
    public function __construct()
    {
        parent::__construct(BP . '/var/log/blackbird_index_monitoring.log', MonologLogger::ERROR);
    }
}
