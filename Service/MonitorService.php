<?php
declare(strict_types = 1);

namespace Blackbird\IndexMonitoring\Service;

use Blackbird\IndexMonitoring\Logger\Logger;
use Blackbird\IndexMonitoring\Model\Checker\IndexStatusChecker;
use Blackbird\IndexMonitoring\Model\Config;
use Blackbird\IndexMonitoring\Model\Deduplicator\DigestStorage;
use Blackbird\IndexMonitoring\Model\Deduplicator\PendingStateStorage;
use Blackbird\IndexMonitoring\Model\Notifier\EmailNotifier;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class MonitorService
{
    public function __construct(
        private readonly Config $config,
        private readonly IndexStatusChecker $checker,
        private readonly EmailNotifier $notifier,
        private readonly DigestStorage $digestStorage,
        private readonly PendingStateStorage $pendingStateStorage,
        private readonly SerializerInterface $serializer,
        private readonly TimezoneInterface $timezone,
        private readonly Logger $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $threshold = $this->config->getThresholdMinutes();
        $idlePendingThreshold = $this->config->getIdlePendingThresholdMinutes();
        $idlePendingThresholdSeconds = max(1, $idlePendingThreshold) * 60;
        $now = time();

        $rawIssues = $this->checker->collectIssues($threshold, $idlePendingThreshold);

        // Update our own first-seen clock for idle_pending mviews.
        // We cannot rely on mview_state.updated_at because Magento does not always refresh it.
        $firstSeenMap = $this->pendingStateStorage->getAll();
        $currentPendingIds = [];

        foreach ($rawIssues['mviews'] as $mview) {
            if ($mview['issue_type'] === 'idle_pending') {
                $viewId = $mview['id'];
                $currentPendingIds[] = $viewId;
                if (!isset($firstSeenMap[$viewId])) {
                    // Seed first_seen from mview updated_at if it already exceeds the threshold,
                    // so a genuinely long-pending mview triggers an alert on the first detection.
                    // If updated_at is 0 (uninitialized) or recent, start the clock from now.
                    $mviewUpdated = (int) ($mview['updated'] ?? 0);
                    $firstSeenMap[$viewId] = ($mviewUpdated > 0 && ($now - $mviewUpdated) > $idlePendingThresholdSeconds)
                        ? $mviewUpdated
                        : $now;
                }
            }
        }

        // Remove mviews that are no longer pending (resolved).
        foreach (array_keys($firstSeenMap) as $viewId) {
            if (!in_array($viewId, $currentPendingIds, true)) {
                unset($firstSeenMap[$viewId]);
            }
        }
        $this->pendingStateStorage->save($firstSeenMap);

        // Only keep idle_pending issues that have exceeded the threshold since first detection.
        $issues = $rawIssues;
        $issues['mviews'] = array_values(array_filter(
            $rawIssues['mviews'],
            function (array $mview) use ($firstSeenMap, $now, $idlePendingThresholdSeconds, $idlePendingThreshold): bool {
                if ($mview['issue_type'] !== 'idle_pending') {
                    return true;
                }
                $firstSeen = $firstSeenMap[$mview['id']] ?? $now;
                return ($now - $firstSeen) > $idlePendingThresholdSeconds;
            }
        ));

        // Enrich idle_pending issues with first_seen metadata for the email.
        $issues['mviews'] = array_map(
            function (array $mview) use ($firstSeenMap, $idlePendingThreshold): array {
                if ($mview['issue_type'] !== 'idle_pending') {
                    return $mview;
                }
                $firstSeen = $firstSeenMap[$mview['id']] ?? 0;
                $dt = $firstSeen > 0
                    ? (new \DateTimeImmutable('@' . $firstSeen))->setTimezone(new \DateTimeZone('UTC'))
                    : null;
                return $mview + [
                    'first_seen'    => $firstSeen,
                    'first_seen_at' => $dt ? $this->timezone->date($dt, true, true)->format('Y-m-d H:i:s T') : 'n/a',
                    'threshold_minutes' => $idlePendingThreshold,
                ];
            },
            $issues['mviews']
        );

        $hasIssues = !empty($issues['indexers']) || !empty($issues['mviews']);

        if (!$hasIssues) {
            if ($this->digestStorage->get() !== '') {
                $this->digestStorage->save('');
            }
            return;
        }

        // Hash only stable identifiers so the digest does not change when counters fluctuate.
        $digest = sha1($this->serializer->serialize($this->normalizeForDigest($issues)));

        if ($this->digestStorage->hasChanged($digest)) {
            try {
                $this->notifier->notify($issues);
                $this->digestStorage->save($digest);
                $this->logger->error(sprintf(
                    'Alert sent. Indexers=%d, MViews=%d, threshold=%d min, idlePendingThreshold=%d min',
                    count($issues['indexers']),
                    count($issues['mviews']),
                    $threshold,
                    $idlePendingThreshold
                ));
            } catch (\Throwable $e) {
                $this->logger->error('IndexMonitoring alert sending error: ' . $e->getMessage());
            }
        }
    }

    private function normalizeForDigest(array $issues): array
    {
        return [
            'indexers' => array_map(
                fn($i) => ['id' => $i['id'], 'status' => $i['status']],
                $issues['indexers']
            ),
            'mviews' => array_map(
                fn($m) => ['id' => $m['id'], 'issue_type' => $m['issue_type']],
                $issues['mviews']
            ),
        ];
    }
}
