<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Tonyapps\B2b\Api\Data\RoleInterface;

class Role extends AbstractDb
{
    public const TABLE = 'tonyapps_b2b_role';

    protected function _construct()
    {
        $this->_init(self::TABLE, RoleInterface::ROLE_ID);
    }

    /**
     * Force the stored default flag to match what the caller just saved.
     *
     * Deliberately not in _afterSave, and deliberately writing the row's own flag as well as the
     * others': AbstractDb::save() skips the write entirely when the model reports no data changes,
     * so a role whose in-memory is_default is already 1 — while the database says 0 because
     * another role took the flag — would otherwise silently leave the company with no default at
     * all. One statement sets exactly one default per company, whatever save() did or did not do.
     */
    public function syncDefaultFlag(int $companyId, int $roleId, bool $isDefault): void
    {
        $connection = $this->getConnection();

        if (!$isDefault) {
            $connection->update(
                $this->getMainTable(),
                [RoleInterface::IS_DEFAULT => 0],
                [RoleInterface::ROLE_ID . ' = ?' => $roleId]
            );

            return;
        }

        $connection->update(
            $this->getMainTable(),
            [
                RoleInterface::IS_DEFAULT => new \Zend_Db_Expr(
                    sprintf('IF(%s = %d, 1, 0)', RoleInterface::ROLE_ID, $roleId)
                ),
            ],
            [RoleInterface::COMPANY_ID . ' = ?' => $companyId]
        );
    }

    /**
     * Which of the given companies have at least one role of their own.
     *
     * @param int[] $companyIds
     * @return int[]
     */
    public function getCompanyIdsWithRoles(array $companyIds): array
    {
        if (!$companyIds) {
            return [];
        }

        $connection = $this->getConnection();
        $select = $connection->select()
            ->distinct()
            ->from($this->getMainTable(), RoleInterface::COMPANY_ID)
            ->where(RoleInterface::COMPANY_ID . ' IN (?)', $companyIds);

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * How many members currently hold this role.
     */
    public function countMembers(int $roleId): int
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(CompanyCustomer::TABLE), new \Zend_Db_Expr('COUNT(*)'))
            ->where('role_id = ?', $roleId);

        return (int)$connection->fetchOne($select);
    }

    /**
     * How many members hold each of these roles, in one query; a role nobody holds is left out.
     *
     * @param int[] $roleIds
     * @return array<int, int> member count keyed by role ID
     */
    public function countMembersByRole(array $roleIds): array
    {
        if (!$roleIds) {
            return [];
        }

        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(CompanyCustomer::TABLE), ['role_id', new \Zend_Db_Expr('COUNT(*)')])
            ->where('role_id IN (?)', $roleIds)
            ->group('role_id');

        return array_map('intval', $connection->fetchPairs($select));
    }
}
