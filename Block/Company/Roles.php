<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Block\Company;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Tonyapps\B2b\Api\Data\RoleInterface;
use Tonyapps\B2b\Api\RoleRepositoryInterface;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2b\Model\Permissions;
use Tonyapps\B2b\Model\ResourceModel\Role as RoleResource;

/**
 * The company's roles, and the form for adding or editing one.
 */
class Roles extends Template
{
    public function __construct(
        Context $context,
        private readonly CompanyContext $companyContext,
        private readonly RoleRepositoryInterface $roleRepository,
        private readonly RoleResource $roleResource,
        private readonly Permissions $permissions,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return RoleInterface[]
     */
    public function getRoles(): array
    {
        $company = $this->companyContext->getCompany();

        return $company === null ? [] : $this->roleRepository->getByCompanyId((int)$company->getId());
    }

    public function canManage(): bool
    {
        return $this->companyContext->isAllowed(Permissions::ROLES_MANAGE);
    }

    /**
     * @return array<string, array<string, \Magento\Framework\Phrase>>
     */
    public function getGroupedPermissions(): array
    {
        return $this->permissions->getGrouped();
    }

    public function getPermissionLabels(RoleInterface $role): string
    {
        $labels = [];
        foreach ($this->permissions->getGrouped() as $group) {
            foreach ($group as $permission => $label) {
                if ($role->hasPermission($permission)) {
                    $labels[] = (string)$label;
                }
            }
        }

        return $labels ? implode(', ', $labels) : (string)__('Nothing beyond seeing the company');
    }

    public function getMemberCount(RoleInterface $role): int
    {
        return $this->roleResource->countMembers((int)$role->getId());
    }

    public function getSaveUrl(): string
    {
        return $this->getUrl('b2b/role/save');
    }

    public function getDeleteUrl(RoleInterface $role): string
    {
        return $this->getUrl('b2b/role/delete', ['role_id' => $role->getId()]);
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('b2b/company/index');
    }

    /**
     * Roles as JSON so the edit buttons can fill the form without a round trip.
     */
    public function getRolesJson(): string
    {
        $roles = [];
        foreach ($this->getRoles() as $role) {
            $roles[(int)$role->getId()] = [
                'role_id' => (int)$role->getId(),
                'role_name' => (string)$role->getRoleName(),
                'is_default' => $role->isDefault(),
                'permissions' => $role->getPermissions(),
            ];
        }

        return json_encode($roles, JSON_THROW_ON_ERROR);
    }
}
