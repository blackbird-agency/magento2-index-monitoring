<?php
declare(strict_types=1);

namespace Blackbird\IndexMonitoring\Model\Checker;

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
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * @return array{indexers: array<int, array<string, mixed>>, mviews: array<int, array<string, mixed>>}
     */
    public function collectIssues(int $thresholdMinutes): array
    {
        $thresholdSeconds = max(1, $thresholdMinutes) * 60;
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
            $status = $mviewState->getStatus();
            $mode = (string) $mviewState->getMode();
            $updated = $this->dateTime->timestamp($mviewState->getUpdated());
            $viewId = method_exists($mviewState, 'getViewId') ? (string) $mviewState->getViewId() : '';

            // Magento core defines statuses: idle, working, suspended. We also consider custom 'error' value if present.
            if (in_array($status, [MviewStateInterface::STATUS_SUSPENDED, 'error'], true)) {
                $mviewIssues[] = [
                    'id' => $viewId,
                    'status' => $status,
                    'mode' => $mode,
                    'updated' => $updated,
                    'updated_at' => $this->formatTs($updated),
                ];
                continue;
            }

            if ($status === MviewStateInterface::STATUS_WORKING && ($now - $updated) > $thresholdSeconds) {
                $mviewIssues[] = [
                    'id' => $viewId,
                    'status' => $status,
                    'mode' => $mode,
                    'updated' => $updated,
                    'updated_at' => $this->formatTs($updated),
                    'threshold_minutes' => $thresholdMinutes,
                ];
            }
        }

        return [
            'indexers' => $indexerIssues,
            'mviews' => $mviewIssues,
        ];
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
