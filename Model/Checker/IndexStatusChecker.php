<?php
declare(strict_types=1);

namespace Blackbird\IndexMonitoring\Model\Checker;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Indexer\Model\Indexer\CollectionFactory as IndexerCollectionFactory;
use Magento\Framework\Mview\View\State\CollectionFactory as MviewStateCollectionFactory;
use Magento\Framework\Mview\View\StateInterface as MviewStateInterface;
use Magento\Framework\Indexer\StateInterface as IndexerStateInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class IndexStatusChecker
{
    public function __construct(
        private readonly IndexerCollectionFactory $indexerCollectionFactory,
        private readonly MviewStateCollectionFactory $mviewStateCollectionFactory,
        private readonly TimezoneInterface $timezone,
        private readonly DateTime $dateTime,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @return array{indexers: array<int, array<string, mixed>>, mviews: array<int, array<string, mixed>>}
     */
    public function collectIssues(int $thresholdMinutes, int $idlePendingThresholdMinutes = 30): array
    {
        $thresholdSeconds = max(1, $thresholdMinutes) * 60;
        $idlePendingThresholdSeconds = max(1, $idlePendingThresholdMinutes) * 60;
        $now = time();

        $indexerIssues = [];
        $indexerCollection = $this->indexerCollectionFactory->create();
        $indexerCollection->load();

        foreach ($indexerCollection->getItems() as $indexer) {
            $state = $indexer->getState();
            if (!$state) {
                continue;
            }
            $status = $state->getStatus();
            $updated = $this->dateTime->timestamp($state->getUpdated());

            if ($status === IndexerStateInterface::STATUS_WORKING && ($now - $updated) > $thresholdSeconds) {
                $indexerIssues[] = [
                    'id' => (string) $indexer->getId(),
                    'status' => $status,
                    'updated' => $updated,
                    'updated_at' => $this->formatTs($updated),
                    'threshold_minutes' => $thresholdMinutes,
                ];
            }
        }

        $mviewIssues = [];
        $mviewCollection = $this->mviewStateCollectionFactory->create();
        $mviewCollection->load();

        foreach ($mviewCollection->getItems() as $mviewState) {
            /** @var MviewStateInterface $mviewState */
            $status  = $mviewState->getStatus();
            $mode    = (string) $mviewState->getMode();
            $updated = $this->dateTime->timestamp($mviewState->getUpdated());
            $viewId  = method_exists($mviewState, 'getViewId') ? (string) $mviewState->getViewId() : '';

            // Magento core defines statuses: idle, working, suspended. We also consider custom 'error' value if present.
            if (in_array($status, ['error'], true)) {
                $mviewIssues[] = $this->buildMviewBaseIssue($viewId, 'error', $status, $mode, $updated);
                continue;
            }

            if ($status === MviewStateInterface::STATUS_WORKING && ($now - $updated) > $thresholdSeconds) {
                $mviewIssues[] = $this->buildMviewBaseIssue($viewId, 'working_stuck', $status, $mode, $updated)
                    + ['threshold_minutes' => $thresholdMinutes];
            }

            // Detect idle mviews that have unprocessed changelog entries for too long.
            if ($status === MviewStateInterface::STATUS_IDLE
                && $viewId !== ''
                && ($now - $updated) > $idlePendingThresholdSeconds
            ) {
                $connection = $this->resourceConnection->getConnection();
                $pendingVersions = $this->fetchPendingVersionCount($viewId, (int) $mviewState->getVersionId(), $connection);
                if ($pendingVersions > 0) {
                    $mviewIssues[] = $this->buildMviewBaseIssue($viewId, 'idle_pending', $status, $mode, $updated)
                        + ['pending_versions' => $pendingVersions, 'threshold_minutes' => $idlePendingThresholdMinutes];
                }
            }
        }

        return [
            'indexers' => $indexerIssues,
            'mviews' => $mviewIssues,
        ];
    }

    private function buildMviewBaseIssue(string $viewId, string $issueType, string $status, string $mode, int $updated): array
    {
        return [
            'id'         => $viewId,
            'issue_type' => $issueType,
            'status'     => $status,
            'mode'       => $mode,
            'updated'    => $updated,
            'updated_at' => $this->formatTs($updated),
        ];
    }

    private function fetchPendingVersionCount(string $viewId, int $currentVersionId, AdapterInterface $connection): int
    {
        $changelogTable = $this->resourceConnection->getTableName($viewId . '_cl');
        if (!$connection->isTableExists($changelogTable)) {
            return 0;
        }

        try {
            $select       = $connection->select()->from($changelogTable, [new \Zend_Db_Expr('MAX(version_id)')]);
            $maxVersionId = (int) $connection->fetchOne($select);
            return max(0, $maxVersionId - $currentVersionId);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function formatTs(int $ts): string
    {
        if ($ts <= 0) {
            return 'n/a';
        }

        $dt = (new \DateTimeImmutable('@' . $ts))->setTimezone(new \DateTimeZone('UTC'));
        // Convert to configured timezone
        return $this->timezone->date($dt, true, true)->format('Y-m-d H:i:s T');
    }
}
