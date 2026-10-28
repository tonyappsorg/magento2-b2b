<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Customer-to-company link table.
 *
 * A plain link table rather than a customer EAV attribute: the company shows up in the customer
 * grid with one join, and there is no attribute row per customer to carry around.
 */
class CompanyCustomer extends AbstractDb
{
    public const TABLE = 'tonyapps_b2b_company_customer';

    protected function _construct()
    {
        $this->_init(self::TABLE, 'customer_id');
    }

    /**
     * Move a customer into a company. A customer belongs to at most one, so this overwrites.
     */
    public function assign(int $customerId, int $companyId, ?int $roleId = null): void
    {
        $this->getConnection()->insertOnDuplicate(
            $this->getMainTable(),
            ['customer_id' => $customerId, 'company_id' => $companyId, 'role_id' => $roleId],
            ['company_id', 'role_id']
        );
    }

    /**
     * Change only the role, leaving the company membership as it is.
     */
    public function setRoleId(int $customerId, ?int $roleId): void
    {
        $this->getConnection()->update(
            $this->getMainTable(),
            ['role_id' => $roleId],
            ['customer_id = ?' => $customerId]
        );
    }

    /**
     * The customer's own role, or NULL when they rely on their company's default.
     */
    public function getRoleId(int $customerId): ?int
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), 'role_id')
            ->where('customer_id = ?', $customerId);

        $roleId = $connection->fetchOne($select);

        return $roleId === false || $roleId === null || $roleId === '' ? null : (int)$roleId;
    }

    /**
     * Roles held by several customers in one query; customers on the company default are absent.
     *
     * @param int[] $customerIds
     * @return array<int, int>
     */
    public function getRoleIds(array $customerIds): array
    {
        if (!$customerIds) {
            return [];
        }
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['customer_id', 'role_id'])
            ->where('customer_id IN (?)', $customerIds)
            ->where('role_id IS NOT NULL');

        return array_map('intval', $connection->fetchPairs($select));
    }

    /**
     * Remove a customer from whichever company they are in.
     */
    public function unassign(int $customerId): void
    {
        $this->getConnection()->delete($this->getMainTable(), ['customer_id = ?' => $customerId]);
    }

    public function getCompanyId(int $customerId): ?int
    {
        return $this->getCompanyIds([$customerId])[$customerId] ?? null;
    }

    /**
     * @param int[] $customerIds
     * @return array<int, int> company ID keyed by customer ID, omitting customers without one
     */
    public function getCompanyIds(array $customerIds): array
    {
        if (!$customerIds) {
            return [];
        }

        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['customer_id', 'company_id'])
            ->where('customer_id IN (?)', $customerIds);

        return array_map('intval', $connection->fetchPairs($select));
    }

    /**
     * Customer IDs belonging to any of the given companies.
     *
     * @param int[] $companyIds
     * @return int[]
     */
    public function getMemberIds(array $companyIds): array
    {
        if (!$companyIds) {
            return [];
        }

        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), 'customer_id')
            ->where('company_id IN (?)', $companyIds);

        return array_map('intval', $connection->fetchCol($select));
    }
}
