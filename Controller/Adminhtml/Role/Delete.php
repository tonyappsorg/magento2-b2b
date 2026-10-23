<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Adminhtml\Role;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Tonyapps\B2b\Api\Data\RoleInterface;

class Delete extends AbstractRole implements HttpPostActionInterface
{
    public function execute(): Redirect
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();
        $roleId = (int)$this->getRequest()->getParam(RoleInterface::ROLE_ID);
        $companyId = 0;

        try {
            $role = $this->roleRepository->getById($roleId);
            $companyId = (int)$role->getCompanyId();
            $this->roleRepository->delete($role);
            $this->messageManager->addSuccessMessage(__('The role has been deleted.'));
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('That role no longer exists.'));
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $resultRedirect->setPath(
            'tonyapps_b2b/role/index',
            $companyId ? [RoleInterface::COMPANY_ID => $companyId] : []
        );
    }
}
