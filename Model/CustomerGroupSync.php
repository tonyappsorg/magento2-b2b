<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model;

use Magento\Customer\Api\GroupManagementInterface;
use Magento\Customer\Model\Indexer\Processor as CustomerIndexer;
use Magento\Framework\App\ResourceConnection;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Model\ResourceModel\Company as CompanyResource;
use Tonyapps\B2b\Model\ResourceModel\CompanyCustomer;

/**
 * Keeps company members in the customer group their company resolves to.
 *
 * This is the whole of "personalised pricing and promotions by customer group": tier prices,
 * catalog price rules and cart price rules are already group-aware, so assigning the group is the
 * only thing this module has to do.
 *
 * One rule decides everything here: **a company confers its resolved group on its members only
 * while it is Active.** A company that resolves to no group never touches anyone. A company that
 * would resolve to a group but is pending, rejected or blocked puts its members in the default
 * group instead — so a self-registered company cannot hand itself wholesale pricing before an
 * administrator has approved it.
 *
 * ponytail: group changes are written straight to customer_entity.group_id and the affected rows
 * are re-indexed by hand, instead of loading and saving every customer through
 * CustomerRepository. A company can have thousands of members and a repository save each would
 * take minutes; the trade is that customer_save_* observers do not fire for a group change made
 * this way. Switch to the repository if something ever needs to observe it.
 */
class CustomerGroupSync
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly CompanyResource $companyResource,
        private readonly CompanyCustomer $companyCustomer,
        private readonly SettingResolver $settingResolver,
        private readonly GroupManagementInterface $groupManagement,
        private readonly CustomerIndexer $customerIndexer
    ) {
    }

    /**
     * Re-apply groups across a company and its descendants.
     *
     * Descendants that set their own group keep it, so each company in the subtree is resolved
     * separately; companies that land on the same group are updated in one statement.
     */
    public function syncCompanyTree(int $companyId): void
    {
        $this->applyToTree($companyId, false);
    }

    /**
     * Take a company's group off its members — for a company that is going away, or that has
     * stopped being active.
     */
    public function releaseCompanyTree(int $companyId): void
    {
        $this->applyToTree($companyId, true);
    }

    /**
     * Apply the group that follows from a customer's company membership.
     *
     * Joining a company that manages no group leaves the customer's group alone; leaving a
     * company, or joining one that is not active, puts them in the default group.
     */
    public function syncCustomer(int $customerId, ?int $companyId): void
    {
        if ($companyId === null) {
            $this->resetToDefaultGroup([$customerId]);

            return;
        }

        $groupId = $this->settingResolver->resolveCustomerGroupId(
            $this->companyResource->getPathById($companyId)
        );

        if ($groupId === null) {
            // Nothing on this company's path manages a group, so nothing here should move.
            return;
        }

        if (!$this->isActive($companyId)) {
            $this->resetToDefaultGroup([$customerId]);

            return;
        }

        $this->reindex($this->applyGroup([$customerId], $groupId));
    }

    /**
     * Put customers back in the store's default group, so an ex-member does not keep company
     * pricing.
     *
     * @param int[] $customerIds
     */
    public function resetToDefaultGroup(array $customerIds): void
    {
        if (!$customerIds) {
            return;
        }

        $this->reindex($this->applyGroup($customerIds, $this->getDefaultGroupId()));
    }

    /**
     * Walk a subtree and put every company's members where they belong.
     *
     * @param bool $release treat every company as no longer entitled to confer its group
     */
    private function applyToTree(int $companyId, bool $release): void
    {
        $subtree = $this->companyResource->getSubtree($companyId);
        if (!$subtree) {
            return;
        }

        $paths = array_map(static fn(array $row): string => $row['path'], $subtree);
        $resolved = $this->settingResolver->resolveCustomerGroupIds($paths);

        $companyIdsByGroup = [];
        foreach ($subtree as $subtreeCompanyId => $row) {
            if ($resolved[$subtreeCompanyId] === null) {
                // No company on this path manages a group, so its members are not ours to move.
                continue;
            }

            $entitled = !$release && $row['status'] === CompanyInterface::STATUS_ACTIVE;
            $groupId = $entitled ? $resolved[$subtreeCompanyId] : $this->getDefaultGroupId();
            $companyIdsByGroup[$groupId][] = (int)$subtreeCompanyId;
        }

        $changed = [];
        foreach ($companyIdsByGroup as $groupId => $companyIds) {
            $changed[] = $this->applyGroup($this->companyCustomer->getMemberIds($companyIds), (int)$groupId);
        }

        $this->reindex($changed ? array_merge(...$changed) : []);
    }

    private function isActive(int $companyId): bool
    {
        return $this->companyResource->getStatusById($companyId) === CompanyInterface::STATUS_ACTIVE;
    }

    private function getDefaultGroupId(): int
    {
        return (int)$this->groupManagement->getDefaultGroup()->getId();
    }

    /**
     * Set the group on customers that are not already in it.
     *
     * @param int[] $customerIds
     * @return int[] the customers actually changed
     */
    private function applyGroup(array $customerIds, int $groupId): array
    {
        if (!$customerIds) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('customer_entity');

        $select = $connection->select()
            ->from($table, 'entity_id')
            ->where('entity_id IN (?)', $customerIds)
            ->where('group_id != ?', $groupId);
        $changed = array_map('intval', $connection->fetchCol($select));

        if ($changed) {
            $connection->update($table, ['group_id' => $groupId], ['entity_id IN (?)' => $changed]);
        }

        return $changed;
    }

    /**
     * @param int[] $customerIds
     */
    private function reindex(array $customerIds): void
    {
        if (!$customerIds || $this->customerIndexer->isIndexerScheduled()) {
            return;
        }

        $this->customerIndexer->reindexList($customerIds);
    }
}
