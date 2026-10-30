<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model;

use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Model\ResourceModel\Company as CompanyResource;
use Tonyapps\B2b\Model\ResourceModel\CompanyPayment;
use Tonyapps\B2b\Model\ResourceModel\Role as RoleResource;

/**
 * Resolves settings a company may inherit from its ancestors.
 *
 * A company stores NULL for a setting it does not own; the effective value is the nearest
 * non-NULL value walking up the materialized path. Phase 1 only resolves the customer group;
 * payment restrictions and credit join the same walk later.
 */
class SettingResolver
{
    public function __construct(
        private readonly CompanyResource $companyResource,
        private readonly RoleResource $roleResource,
        private readonly CompanyPayment $companyPayment
    ) {
    }

    /**
     * Effective customer group of a company, or NULL when neither it nor any ancestor sets one.
     *
     * @param string|null $path materialized path of the company, e.g. "1/4/9"
     */
    public function resolveCustomerGroupId(?string $path): ?int
    {
        return $this->resolveCustomerGroupIds([$path])[0];
    }

    /**
     * Batch form of resolveCustomerGroupId: one query for every ancestor of every given path.
     *
     * @param array<array-key, string|null> $paths materialized paths keyed however the caller likes
     * @return array<array-key, int|null> resolved group IDs under the same keys
     */
    public function resolveCustomerGroupIds(array $paths): array
    {
        $exploded = array_map([$this, 'explodePath'], $paths);
        $ancestorIds = $exploded ? array_values(array_unique(array_merge(...array_values($exploded)))) : [];
        $groupIds = $this->companyResource->getColumnByIds(CompanyInterface::CUSTOMER_GROUP_ID, $ancestorIds);

        $resolved = [];
        foreach ($exploded as $key => $pathIds) {
            $resolved[$key] = null;
            // Nearest ancestor wins, so walk from the company itself back up to the root.
            foreach (array_reverse($pathIds) as $companyId) {
                if (($groupIds[$companyId] ?? null) !== null) {
                    $resolved[$key] = $groupIds[$companyId];
                    break;
                }
            }
        }

        return $resolved;
    }

    /**
     * The payment methods a company may use, or NULL when it is not restricted at all.
     *
     * Walks the path for the nearest company that has made a decision: "inherit" keeps looking
     * upwards, "allow every method" ends the walk with no restriction, and "restrict" ends it with
     * that company's own list. A company set to restrict but given no methods allows none — an odd
     * configuration, but the administrator's to make, not ours to second-guess.
     *
     * @return string[]|null allowed method codes, or NULL for "no restriction"
     */
    public function resolvePaymentMethods(?string $path): ?array
    {
        $pathIds = $this->explodePath($path);
        if (!$pathIds) {
            return null;
        }

        $modes = $this->companyResource->getColumnByIds(CompanyInterface::PAYMENT_MODE, $pathIds);

        foreach (array_reverse($pathIds) as $companyId) {
            $mode = $modes[$companyId] ?? CompanyInterface::PAYMENT_MODE_INHERIT;

            if ($mode === CompanyInterface::PAYMENT_MODE_ALL) {
                return null;
            }

            if ($mode === CompanyInterface::PAYMENT_MODE_RESTRICT) {
                return $this->companyPayment->getMethodCodes($companyId);
            }
        }

        return null;
    }

    /**
     * Which company's roles apply to the company at the end of this path.
     *
     * Roles are inherited whole, not merged: a company that defines any role of its own uses only
     * those, and one that defines none borrows the nearest ancestor's set. Merging two companies'
     * role lists would produce names nobody created and defaults nobody chose.
     *
     * @return int|null the company owning the applicable roles, NULL when nothing on the path has any
     */
    public function resolveRoleOwnerId(?string $path): ?int
    {
        $pathIds = $this->explodePath($path);
        if (!$pathIds) {
            return null;
        }

        $withRoles = $this->roleResource->getCompanyIdsWithRoles($pathIds);

        foreach (array_reverse($pathIds) as $companyId) {
            if (in_array($companyId, $withRoles, true)) {
                return $companyId;
            }
        }

        return null;
    }

    /**
     * Whether a role defined by $roleCompanyId may be used inside the company at $path — true for
     * the company's own roles and for any ancestor's.
     */
    public function isRoleUsable(?string $path, int $roleCompanyId): bool
    {
        return in_array($roleCompanyId, $this->explodePath($path), true);
    }

    /**
     * @param string|null $path
     * @return int[]
     */
    private function explodePath(?string $path): array
    {
        if ($path === null || $path === '') {
            return [];
        }

        return array_values(
            array_filter(
                array_map('intval', explode(CompanyInterface::PATH_SEPARATOR, $path)),
                static fn(int $id): bool => $id > 0
            )
        );
    }
}
