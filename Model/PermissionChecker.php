<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Api\Data\RoleInterface;
use Tonyapps\B2b\Api\RoleRepositoryInterface;
use Tonyapps\B2b\Model\ResourceModel\Company as CompanyResource;
use Tonyapps\B2b\Model\ResourceModel\CompanyCustomer;

/**
 * Decides whether a company member may do something.
 *
 * The rules, in the order they are applied:
 *   1. A company that is not Active grants nothing at all — a pending or blocked company must not
 *      let its users carry on managing it.
 *   2. The company administrator may do everything. They are the one person who could otherwise
 *      lock themselves out of their own company by editing a role.
 *   3. Otherwise the member's own role decides; a member with no role of their own falls back to
 *      the company's default role.
 *   4. With no role and no default role, a member can still see the company page — nothing else.
 */
class PermissionChecker
{
    /**
     * @var array<int, string[]>
     */
    private array $cache = [];

    public function __construct(
        private readonly CompanyCustomer $companyCustomer,
        private readonly RoleRepositoryInterface $roleRepository,
        private readonly Permissions $permissions,
        private readonly CompanyResource $companyResource
    ) {
    }

    /**
     * Whether a customer holds a permission within their company.
     */
    public function isAllowed(?int $customerId, ?CompanyInterface $company, string $permission): bool
    {
        if ($customerId === null || $company === null) {
            return false;
        }

        return in_array($permission, $this->getPermissions($customerId, $company), true);
    }

    /**
     * Everything a customer may do in their company.
     *
     * @return string[]
     */
    public function getPermissions(int $customerId, CompanyInterface $company): array
    {
        $cacheKey = $customerId;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        return $this->cache[$cacheKey] = $this->resolve($customerId, $company);
    }

    /**
     * @return string[]
     */
    private function resolve(int $customerId, CompanyInterface $company): array
    {
        // A blocked or pending parent switches off its whole subtree.
        if (!$this->companyResource->isActivePath((int)$company->getId())) {
            return [];
        }

        if ($company->getAdminCustomerId() === $customerId) {
            return $this->permissions->getAll();
        }

        $role = $this->getRoleFor($customerId, (int)$company->getId());
        if ($role === null) {
            return [Permissions::ALWAYS_GRANTED];
        }

        return $this->permissions->normalise(
            array_merge($role->getPermissions(), [Permissions::ALWAYS_GRANTED])
        );
    }

    private function getRoleFor(int $customerId, int $companyId): ?RoleInterface
    {
        $roleId = $this->companyCustomer->getRoleId($customerId);

        if ($roleId !== null) {
            try {
                $role = $this->roleRepository->getById($roleId);
                // The role must be this company's own or one it inherits from an ancestor; a
                // stale link to some unrelated company's role must not grant anything.
                if ($this->roleRepository->isUsableBy($role, $companyId)) {
                    return $role;
                }
            } catch (NoSuchEntityException) {
                // Fall through to the company default.
            }
        }

        return $this->roleRepository->getDefaultForCompany($companyId);
    }

}
