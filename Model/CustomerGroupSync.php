<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model;

use Magento\Customer\Api\GroupManagementInterface;
use Magento\Customer\Model\Customer\NotificationStorage;
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
 * while it and every company above it are Active.** A company that resolves to no group never touches anyone. A company that
 * would resolve to a group but is pending, rejected or blocked puts its members in the default
 * group instead — so a self-registered company cannot hand itself wholesale pricing before an
 * administrator has approved it.
 *
 * ponytail: group changes are written straight to customer_entity.group_id and the affected rows
 * are re-indexed by hand, instead of loading and saving every customer through
 * CustomerRepository. A company can have thousands of members and a repository save each would
 * take minutes; the trade is that customer_save_* observers do not fire for a group change made
 * this way. Switch to the repository if something ever needs to observe it. Logged-in sessions are
 * still refreshed, through core's customer notification storage.
 */
class CustomerGroupSync
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly CompanyResource $companyResource,
        private readonly CompanyCustomer $companyCustomer,
        private readonly SettingResolver $settingResolver,
        private readonly GroupManagementInterface $groupManagement,
        private readonly CustomerIndexer $customerIndexer,
        private readonly NotificationStorage $notificationStorage
    ) {
    }

    /**
     * Re-apply groups across a company and its descendants.
     *
     * Descendants that set their own group keep it, so each company in the subtree is resolved
     * separately; companies that land on the same group are updated in one statement.
     *
     * @param int|null $previousGroupId the group the company resolved to before the change that
     *        triggered this sync; members of companies that now resolve to no group and are still
     *        in it go back to the default group, so clearing the group takes its pricing away.
     */
    public function syncCompanyTree(int $companyId, ?int $previousGroupId = null): void
    {
        $this->applyToTree($companyId, false, $previousGroupId);
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
     * Joining an active company that manages a group applies it; joining one that is not active
     * puts them in the default group. Otherwise only the previous company's group is taken back: a
     * customer still in the group their old company gave them returns to the default group, while
     * a group an administrator set by hand, or one no company ever gave, is left alone.
     */
    public function syncCustomer(int $customerId, ?int $companyId, ?int $previousCompanyId = null): void
    {
        $groupId = $this->resolveGroupId($companyId);
        if ($groupId !== null) {
            if ($this->isActive((int)$companyId)) {
                $this->reindex($this->applyGroup([$customerId], $groupId));
            } else {
                $this->resetToDefaultGroup([$customerId]);
            }

            return;
        }

        $previousGroupId = $this->resolveGroupId($previousCompanyId);
        if ($previousGroupId !== null && $this->getCurrentGroupId($customerId) === $previousGroupId) {
            $this->resetToDefaultGroup([$customerId]);
        }
    }

    private function resolveGroupId(?int $companyId): ?int
    {
        if ($companyId === null) {
            return null;
        }
        $path = $this->companyResource->getPathById($companyId);

        return $path === null ? null : $this->settingResolver->resolveCustomerGroupId($path);
    }

    private function getCurrentGroupId(int $customerId): int
    {
        $connection = $this->resourceConnection->getConnection();

        return (int)$connection->fetchOne(
            $connection->select()
                ->from($this->resourceConnection->getTableName('customer_entity'), 'group_id')
                ->where('entity_id = ?', $customerId)
        );
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
    private function applyToTree(int $companyId, bool $release, ?int $previousGroupId = null): void
    {
        $subtree = $this->companyResource->getSubtree($companyId);
        if (!$subtree) {
            return;
        }

        $paths = array_map(static fn(array $row): string => $row['path'], $subtree);
        $resolved = $this->settingResolver->resolveCustomerGroupIds($paths);
        // A blocked ancestor above the subtree switches it off too: fetch the statuses of those as well.
        $statuses = array_map(static fn(array $row): int => (int)$row['status'], $subtree);
        $ancestorIds = array_diff(
            array_map('intval', explode(CompanyInterface::PATH_SEPARATOR, implode(CompanyInterface::PATH_SEPARATOR, $paths))),
            array_keys($subtree)
        );
        if ($ancestorIds) {
            $statuses += $this->companyResource->getStatusesByIds(array_values($ancestorIds));
        }

        $companyIdsByGroup = [];
        $ungrouped = [];
        foreach ($subtree as $subtreeCompanyId => $row) {
            if ($resolved[$subtreeCompanyId] === null) {
                // No company on this path manages a group, so its members are not ours to move —
                // except out of the group it managed until now.
                $ungrouped[] = (int)$subtreeCompanyId;
                continue;
            }

            $entitled = !$release && $this->isPathActive($row['path'], $statuses);
            $groupId = $entitled ? $resolved[$subtreeCompanyId] : $this->getDefaultGroupId();
            $companyIdsByGroup[$groupId][] = (int)$subtreeCompanyId;
        }

        $changed = [];
        foreach ($companyIdsByGroup as $groupId => $companyIds) {
            $changed[] = $this->applyGroup($this->companyCustomer->getMemberIds($companyIds), (int)$groupId);
        }

        if ($previousGroupId !== null && $ungrouped) {
            $stillInOldGroup = $this->filterByGroup($this->companyCustomer->getMemberIds($ungrouped), $previousGroupId);
            $changed[] = $this->applyGroup($stillInOldGroup, $this->getDefaultGroupId());
        }

        $this->reindex($changed ? array_merge(...$changed) : []);
    }

    private function isActive(int $companyId): bool
    {
        return $this->companyResource->isActivePath($companyId);
    }

    /**
     * @param array<int, int> $statuses
     */
    private function isPathActive(string $path, array $statuses): bool
    {
        foreach (explode(CompanyInterface::PATH_SEPARATOR, $path) as $id) {
            if (($statuses[(int)$id] ?? null) !== CompanyInterface::STATUS_ACTIVE) {
                return false;
            }
        }

        return true;
    }

    /**
     * The customers among these who are in the given group; a group set by hand is left alone.
     *
     * @param int[] $customerIds
     * @return int[]
     */
    private function filterByGroup(array $customerIds, int $groupId): array
    {
        if (!$customerIds) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName('customer_entity'), 'entity_id')
            ->where('entity_id IN (?)', $customerIds)
            ->where('group_id = ?', $groupId);

        return array_map('intval', $connection->fetchCol($select));
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
            // The raw update skips core's save, so tell logged-in sessions to reload the customer and pick
            // up the new group (prices, cart rules) on their next request.
            foreach ($changed as $customerId) {
                $this->notificationStorage->add(NotificationStorage::UPDATE_CUSTOMER_SESSION, $customerId);
            }
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
