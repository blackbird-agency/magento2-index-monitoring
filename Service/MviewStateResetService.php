<?php
declare(strict_types=1);

namespace Blackbird\IndexMonitoring\Service;

use Exception;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Mview\View\State\CollectionFactory as MviewStateCollectionFactory;
use Magento\Framework\Mview\View\StateInterface as MviewStateInterface;

class MviewStateResetService
{
    public function __construct(
        private readonly MviewStateCollectionFactory $mviewStateCollectionFactory,
        private readonly IndexerRegistry $indexerRegistry
    ) {
    }

    /**
     * @param array $indexerIds Array of indexer IDs
     * @return int Number of views reset
     * @throws Exception
     */
    public function resetToIdle(array $indexerIds): int
    {
        if (empty($indexerIds)) {
            return 0;
        }

        $viewIds = [];
        foreach ($indexerIds as $indexerId) {
            try {
                $indexer = $this->indexerRegistry->get($indexerId);
                $view = $indexer->getView();
                if ($view) {
                    $viewIds[] = $view->getId();
                }
            } catch (Exception) {
                // Indexer might not have a view, skip it
                continue;
            }
        }

        if (empty($viewIds)) {
            return 0;
        }

        $count = 0;
        $collection = $this->mviewStateCollectionFactory->create();

        foreach ($collection->getItems() as $mviewState) {
            /** @var MviewStateInterface $mviewState */
            $viewId = method_exists($mviewState, 'getViewId') ? $mviewState->getViewId() : '';

            if (in_array($viewId, $viewIds, true)) {
                $mviewState->setStatus(MviewStateInterface::STATUS_IDLE);
                $mviewState->save();
                $count++;
            }
        }

        return $count;
    }
}
