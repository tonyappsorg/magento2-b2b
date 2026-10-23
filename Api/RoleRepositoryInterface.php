<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Tonyapps\B2b\Api\Data\RoleInterface;

interface RoleRepositoryInterface
{
    public function save(RoleInterface $role): RoleInterface;

    public function getById(int $roleId): RoleInterface;

    public function delete(RoleInterface $role): bool;

    public function deleteById(int $roleId): bool;

    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;

    /**
     * Every role belonging to a company.
     *
     * @return RoleInterface[]
     */
    public function getByCompanyId(int $companyId): array;

    /**
     * The role given to members who have none of their own, or NULL when neither the company nor
     * any ancestor it inherits roles from has marked one as the default.
     */
    public function getDefaultForCompany(int $companyId): ?RoleInterface;

    /**
     * The roles that actually apply inside a company: its own if it defines any, otherwise the
     * nearest ancestor's. Roles are inherited as a whole set, never merged.
     *
     * @return RoleInterface[]
     */
    public function getEffectiveRoles(int $companyId): array;

    /**
     * Whether a role may be held by a member of this company — its own roles and any ancestor's.
     */
    public function isUsableBy(RoleInterface $role, int $companyId): bool;
}
