<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Adminhtml\Company;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Ui\Component\MassAction\Filter;
use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Model\CompanyStatus;
use Tonyapps\B2b\Model\ResourceModel\Company\CollectionFactory;

/**
 * Approve / reject / block the companies selected in the grid.
 */
class MassStatus extends AbstractCompany implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        CompanyRepositoryInterface $companyRepository,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly CompanyStatus $companyStatus
    ) {
        parent::__construct($context, $companyRepository);
    }

    public function execute(): Redirect
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        $status = $this->getRequest()->getParam('status');
        if (!$this->isKnownStatus($status)) {
            $this->messageManager->addErrorMessage(__('That is not a status a company can be put in.'));

            return $resultRedirect->setPath('*/*/');
        }

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());

            return $resultRedirect->setPath('*/*/');
        }

        $changed = 0;
        $failed = 0;
        /** @var \Tonyapps\B2b\Model\Company $company */
        foreach ($collection as $company) {
            try {
                $changed += $this->companyStatus->change($company, (int)$status) ? 1 : 0;
            } catch (\Exception $exception) {
                $failed++;
                $this->messageManager->addErrorMessage(
                    __('%1: %2', $company->getName(), $exception->getMessage())
                );
            }
        }

        if ($changed) {
            $this->messageManager->addSuccessMessage(__('A total of %1 company(s) have been updated.', $changed));
        } elseif (!$failed) {
            $this->messageManager->addNoticeMessage(__('Those companies were already in that status.'));
        }

        return $resultRedirect->setPath('*/*/');
    }

    private function isKnownStatus(mixed $status): bool
    {
        return is_numeric($status) && in_array(
            (int)$status,
            [
                CompanyInterface::STATUS_PENDING,
                CompanyInterface::STATUS_ACTIVE,
                CompanyInterface::STATUS_REJECTED,
                CompanyInterface::STATUS_BLOCKED,
            ],
            true
        );
    }
}
