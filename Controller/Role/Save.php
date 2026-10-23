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
use Psr\Log\LoggerInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Api\Data\RoleInterface;
use Tonyapps\B2b\Api\RoleRepositoryInterface;
use Tonyapps\B2b\Controller\AbstractCompanyAction;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2b\Model\Permissions;
use Tonyapps\B2b\Model\RoleFactory;

/**
 * Creates or updates one of the company's roles.
 */
class Save extends AbstractCompanyAction implements HttpPostActionInterface
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
        private readonly RoleRepositoryInterface $roleRepository,
        private readonly RoleFactory $roleFactory,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($request, $resultFactory, $companyContext, $customerSession, $messageManager, $url);
    }

    protected function executeForCompany(CompanyInterface $company): ResultInterface
    {
        if (!$this->formKeyValidator->validate($this->request)) {
            return $this->redirect('b2b/role/index');
        }

        $data = (array)$this->request->getPostValue('role', []);
        $roleId = (int)($data[RoleInterface::ROLE_ID] ?? 0);

        try {
            $role = $roleId ? $this->roleRepository->getById($roleId) : $this->roleFactory->create();

            // A company administrator must not be able to reach into another company's roles by
            // posting somebody else's role ID.
            if ($roleId && $role->getCompanyId() !== (int)$company->getId()) {
                throw new LocalizedException(__('That role does not belong to your company.'));
            }

            $role->setCompanyId((int)$company->getId());
            $role->setRoleName(trim((string)($data[RoleInterface::ROLE_NAME] ?? '')));
            $role->setIsDefault(!empty($data[RoleInterface::IS_DEFAULT]));
            $role->setPermissions(array_map('strval', (array)($data['permissions'] ?? [])));

            $this->roleRepository->save($role);
            $this->messageManager->addSuccessMessage(__('The role has been saved.'));
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('That role no longer exists.'));
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Exception $exception) {
            $this->logger->error('Tonyapps_B2b role save failed: ' . $exception->getMessage());
            $this->messageManager->addErrorMessage(__('Something went wrong while saving the role.'));
        }

        return $this->redirect('b2b/role/index');
    }
}
