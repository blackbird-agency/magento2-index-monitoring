<?php
declare(strict_types=1);

namespace Blackbird\IndexMonitoring\Controller\Adminhtml\Mview;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Blackbird\IndexMonitoring\Service\MviewStateResetService;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;

class MassResetState extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_Indexer::index';

    public function __construct(
        Context $context,
        private readonly MviewStateResetService $mviewStateResetService
    ) {
        parent::__construct($context);
    }

    /**
     * Reset mview states to idle
     *
     * @return ResultInterface
     */
    public function execute()
    {
        $indexerIds = $this->getRequest()->getParam('indexer_ids');

        if (!is_array($indexerIds)) {
            $this->messageManager->addErrorMessage(__('Please select indexers.'));
        } else {
            try {
                $count = $this->mviewStateResetService->resetToIdle($indexerIds);

                if ($count > 0) {
                    $this->messageManager->addSuccessMessage(
                        __('%1 materialized view(s) were reset to idle status.', $count)
                    );
                } else {
                    $this->messageManager->addNoticeMessage(
                        __('No materialized views were found to reset.')
                    );
                }
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage(
                    $e,
                    __("We couldn't reset materialized view(s) because of an error.")
                );
            }
        }

        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('indexer/indexer/list');
    }
}
