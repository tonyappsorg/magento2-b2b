<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Adminhtml\Company;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Registry;
use Magento\Ui\Component\MassAction\Filter;
use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Tonyapps\B2b\Model\CompanyFactory;
use Tonyapps\B2b\Model\ResourceModel\Company\CollectionFactory;

/**
 * Deletes the companies selected in the grid.
 *
 * Deepest first, so selecting a whole branch works: a parent cannot be deleted while it still has
 * children, and deleting the children first is exactly what the administrator meant.
 */
class MassDelete extends AbstractCompany implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        CompanyRepositoryInterface $companyRepository,
        CompanyFactory $companyFactory,
        Registry $coreRegistry,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory
    ) {
        parent::__construct($context, $companyRepository, $companyFactory, $coreRegistry);
    }

    public function execute(): Redirect
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());

            return $resultRedirect->setPath('*/*/');
        }

        $companies = $collection->getItems();
        usort(
            $companies,
            static fn($a, $b): int => $b->getLevel() <=> $a->getLevel()
        );

        $deleted = 0;
        foreach ($companies as $company) {
            try {
                $this->companyRepository->delete($company);
                $deleted++;
            } catch (\Exception $exception) {
                $this->messageManager->addErrorMessage(
                    __('%1: %2', $company->getName(), $exception->getMessage())
                );
            }
        }

        if ($deleted) {
            $this->messageManager->addSuccessMessage(__('A total of %1 company(s) have been deleted.', $deleted));
        }

        return $resultRedirect->setPath('*/*/');
    }
}
