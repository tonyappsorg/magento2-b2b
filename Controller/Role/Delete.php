<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Role;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Api\RoleRepositoryInterface;
use Tonyapps\B2b\Controller\AbstractCompanyAction;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2b\Model\Permissions;

/**
 * Deletes one of the company's roles.
 */
class Delete extends AbstractCompanyAction implements HttpPostActionInterface
{
    protected string $requiredPermission = Permissions::ROLES_MANAGE;

    public function __construct(
        RequestInterface $request,
        ResultFactory $resultFactory,
        CompanyContext $companyContext,
        Session $customerSession,
        ManagerInterface $messageManager,
        UrlInterface $url,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly RoleRepositoryInterface $roleRepository
    ) {
        parent::__construct($request, $resultFactory, $companyContext, $customerSession, $messageManager, $url);
    }

    protected function executeForCompany(CompanyInterface $company): ResultInterface
    {
        if (!$this->formKeyValidator->validate($this->request)) {
            return $this->redirect('b2b/role/index');
        }

        try {
            $role = $this->roleRepository->getById((int)$this->request->getParam('role_id'));
            if ($role->getCompanyId() !== (int)$company->getId()) {
                throw new LocalizedException(__('That role does not belong to your company.'));
            }

            $this->roleRepository->delete($role);
            $this->messageManager->addSuccessMessage(__('The role has been deleted.'));
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('That role no longer exists.'));
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $this->redirect('b2b/role/index');
    }
}
