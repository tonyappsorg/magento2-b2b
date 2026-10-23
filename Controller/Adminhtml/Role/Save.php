<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Adminhtml\Role;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Tonyapps\B2b\Api\Data\RoleInterface;
use Tonyapps\B2b\Api\RoleRepositoryInterface;
use Tonyapps\B2b\Model\RoleFactory;

class Save extends AbstractRole implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        RoleRepositoryInterface $roleRepository,
        RoleFactory $roleFactory,
        private readonly DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context, $roleRepository, $roleFactory);
    }

    public function execute(): Redirect
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        $data = $this->getRequest()->getPostValue();
        if (!$data) {
            return $resultRedirect->setPath('tonyapps_b2b/company/index');
        }

        $roleId = (int)($data[RoleInterface::ROLE_ID] ?? 0);
        $companyId = (int)($data[RoleInterface::COMPANY_ID] ?? 0);

        try {
            $role = $roleId ? $this->roleRepository->getById($roleId) : $this->roleFactory->create();
            // The company of an existing role is fixed; moving a role between companies would
            // silently change what its holders can do.
            $role->setCompanyId($roleId ? (int)$role->getCompanyId() : $companyId);
            $role->setRoleName(trim((string)($data[RoleInterface::ROLE_NAME] ?? '')));
            $role->setIsDefault(!empty($data[RoleInterface::IS_DEFAULT]));
            $role->setPermissions(array_map('strval', (array)($data[RoleInterface::PERMISSIONS] ?? [])));

            $this->roleRepository->save($role);
            $this->dataPersistor->clear(self::DATA_PERSISTOR_KEY);
            $this->messageManager->addSuccessMessage(__('The role has been saved.'));

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath(
                    'tonyapps_b2b/role/edit',
                    [RoleInterface::ROLE_ID => $role->getId()]
                );
            }

            return $resultRedirect->setPath(
                'tonyapps_b2b/role/index',
                [RoleInterface::COMPANY_ID => $role->getCompanyId()]
            );
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('That role no longer exists.'));

            return $resultRedirect->setPath('tonyapps_b2b/company/index');
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Exception $exception) {
            $this->messageManager->addExceptionMessage($exception, __('Could not save the role.'));
        }

        $this->dataPersistor->set(self::DATA_PERSISTOR_KEY, $data);

        return $roleId
            ? $resultRedirect->setPath('tonyapps_b2b/role/edit', [RoleInterface::ROLE_ID => $roleId])
            : $resultRedirect->setPath('tonyapps_b2b/role/new', [RoleInterface::COMPANY_ID => $companyId]);
    }
}
