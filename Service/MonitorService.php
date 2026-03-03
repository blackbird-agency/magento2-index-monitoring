<?php
declare(strict_types = 1);

namespace Blackbird\IndexMonitoring\Service;

use Blackbird\IndexMonitoring\Logger\Logger;
use Blackbird\IndexMonitoring\Model\Checker\IndexStatusChecker;
use Blackbird\IndexMonitoring\Model\Config;
use Blackbird\IndexMonitoring\Model\Deduplicator\DigestStorage;
use Blackbird\IndexMonitoring\Model\Notifier\EmailNotifier;

class MonitorService
{
    public function __construct(
        private readonly Config $config,
        private readonly IndexStatusChecker $checker,
        private readonly EmailNotifier $notifier,
        private readonly DigestStorage $digestStorage,
        private readonly Logger $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $threshold = $this->config->getThresholdMinutes();
        $issues    = $this->checker->collectIssues($threshold);

        $hasIssues = !empty($issues['indexers']) || !empty($issues['mviews']);
        $digest    = $hasIssues ? sha1(json_encode($issues, JSON_THROW_ON_ERROR)) : '';

        if (!$hasIssues) {
            // Reset digest so a new issue later will trigger an email
            if ($this->digestStorage->get() !== '') {
                $this->digestStorage->save('');
            }

            return;
        }

        if ($this->digestStorage->hasChanged($digest)) {
            try {
                $this->notifier->notify($issues);
                $this->digestStorage->save($digest);
                $summary = sprintf(
                    'Alert sent. Indexers=%d, MViews=%d, threshold=%d min',
                    count($issues['indexers']),
                    count($issues['mviews']),
                    $threshold
                );
                $this->logger->error($summary);
            } catch (\Throwable $e) {
                $this->logger->error('IndexMonitoring alert sending error: ' . $e->getMessage());
            }
        }
    }
}
