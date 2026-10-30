<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Block\Company;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Api\Data\RoleInterface;
use Tonyapps\B2b\Api\RoleRepositoryInterface;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2b\Model\CompanyUserManagement;
use Tonyapps\B2b\Model\Permissions;
use Tonyapps\B2b\Model\ResourceModel\CompanyCustomer;

/**
 * The company's user list and the add-user form.
 */
class Users extends Template
{
    /**
     * @var array<int, int>|null
     */
    private ?array $roleIds = null;

    public function __construct(
        Context $context,
        private readonly CompanyContext $companyContext,
        private readonly CompanyUserManagement $userManagement,
        private readonly CompanyCustomer $companyCustomer,
        private readonly RoleRepositoryInterface $roleRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return CustomerInterface[]
     */
    public function getUsers(): array
    {
        $company = $this->companyContext->getCompany();

        return $company === null ? [] : $this->userManagement->getUsers((int)$company->getId());
    }

    public function isAdministrator(CustomerInterface $customer): bool
    {
        return $this->companyContext->getCompany()?->getAdminCustomerId() === (int)$customer->getId();
    }

    public function canManage(): bool
    {
        return $this->companyContext->isAllowed(Permissions::USERS_MANAGE);
    }

    /**
     * Roles this company offers, for the per-user role selector.
     *
     * @return RoleInterface[]
     */
    public function getRoles(): array
    {
        $company = $this->companyContext->getCompany();

        return $company === null ? [] : $this->roleRepository->getEffectiveRoles((int)$company->getId());
    }

    /**
     * The role a user holds, or NULL when they follow the company default.
     */
    public function getRoleId(CustomerInterface $customer): ?int
    {
        // One query for the whole list instead of one per row.
        $this->roleIds ??= $this->companyCustomer->getRoleIds(
            array_map(static fn(CustomerInterface $user): int => (int)$user->getId(), $this->getUsers())
        );

        return $this->roleIds[(int)$customer->getId()] ?? null;
    }

    public function getSetRoleUrl(CustomerInterface $customer): string
    {
        return $this->getUrl('b2b/user/role', ['customer_id' => $customer->getId()]);
    }

    public function getAddUrl(): string
    {
        return $this->getUrl('b2b/user/save');
    }

    public function getRemoveUrl(CustomerInterface $customer): string
    {
        return $this->getUrl('b2b/user/delete', ['customer_id' => $customer->getId()]);
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('b2b/company/index');
    }
}
