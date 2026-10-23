<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Adminhtml\Role;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Tonyapps\B2b\Api\Data\RoleInterface;

class Edit extends AbstractRole implements HttpGetActionInterface
{
    public function execute(): ResultInterface
    {
        $roleId = (int)$this->getRequest()->getParam(RoleInterface::ROLE_ID);
        $companyId = (int)$this->getRequest()->getParam(RoleInterface::COMPANY_ID);

        if ($roleId) {
            try {
                $companyId = (int)$this->roleRepository->getById($roleId)->getCompanyId();
            } catch (NoSuchEntityException) {
                $this->messageManager->addErrorMessage(__('That role no longer exists.'));

                /** @var \Magento\Backend\Model\View\Result\Redirect $redirect */
                $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

                return $redirect->setPath('tonyapps_b2b/company/index');
            }
        } elseif (!$companyId) {
            $this->messageManager->addErrorMessage(__('Pick a company to add the role to.'));

            /** @var \Magento\Backend\Model\View\Result\Redirect $redirect */
            $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

            return $redirect->setPath('tonyapps_b2b/company/index');
        }

        /** @var \Magento\Backend\Model\View\Result\Page $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $result->setActiveMenu(self::ADMIN_RESOURCE);
        $result->getConfig()->getTitle()->prepend($roleId ? __('Edit Role') : __('New Role'));

        return $result;
    }
}
