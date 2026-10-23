<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Adminhtml\Company;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Tonyapps\B2b\Api\Data\CompanyInterface;

class Delete extends AbstractCompany implements HttpPostActionInterface
{
    public function execute(): Redirect
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $companyId = (int)$this->getRequest()->getParam(CompanyInterface::ENTITY_ID);

        if (!$companyId) {
            $this->messageManager->addErrorMessage(__('We can\'t find a company to delete.'));

            return $resultRedirect->setPath('*/*/');
        }

        try {
            $this->companyRepository->deleteById($companyId);
            $this->messageManager->addSuccessMessage(__('The company has been deleted.'));

            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Exception $exception) {
            $this->messageManager->addExceptionMessage($exception, __('Could not delete the company.'));
        }

        return $resultRedirect->setPath('*/*/edit', [CompanyInterface::ENTITY_ID => $companyId]);
    }
}
