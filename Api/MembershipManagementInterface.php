<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Api;

interface MembershipManagementInterface
{
    /**
     * Move a customer into a company (or out of one when $companyId is NULL) and apply the
     * resulting customer group.
     */
    public function assign(int $customerId, ?int $companyId, ?int $roleId = null): void;

    /**
     * Give a customer a different role inside the company they already belong to.
     */
    public function setRole(int $customerId, ?int $roleId): void;

    /**
     * Company a customer belongs to, or NULL when they are not a company user.
     */
    public function getCompanyId(int $customerId): ?int;

    /**
     * Company IDs keyed by customer ID, for the customers that have one.
     *
     * @param int[] $customerIds
     * @return array<int, int>
     */
    public function getCompanyIds(array $customerIds): array;
}
