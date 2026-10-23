<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model\ResourceModel;

use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Tonyapps\B2b\Api\Data\CompanyInterface;

/**
 * Company resource model; owns the materialized path.
 *
 * The path can only be known after the row has an ID, so it is written in _afterSave — for a new
 * company, and again whenever the parent changes, together with the paths of every descendant.
 */
class Company extends AbstractDb
{
    public const TABLE = 'tonyapps_b2b_company';

    protected function _construct()
    {
        $this->_init(self::TABLE, CompanyInterface::ENTITY_ID);
    }

    /**
     * Reject a parent that would create a cycle.
     *
     * @param AbstractModel $object
     * @return $this
     * @throws LocalizedException
     */
    protected function _beforeSave(AbstractModel $object)
    {
        $parentId = $object->getData(CompanyInterface::PARENT_ID);
        $companyId = (int)$object->getId();

        if ($parentId === null || $parentId === '') {
            return parent::_beforeSave($object);
        }

        $parentId = (int)$parentId;

        if ($companyId && $parentId === $companyId) {
            throw new LocalizedException(__('A company cannot be its own parent.'));
        }

        $parentPath = $this->getPathById($parentId);
        if ($parentPath === null) {
            throw new LocalizedException(__('The selected parent company does not exist.'));
        }

        if ($companyId && in_array($companyId, array_map('intval', explode('/', $parentPath)), true)) {
            throw new LocalizedException(__('A company cannot be moved under one of its own children.'));
        }

        return parent::_beforeSave($object);
    }

    /**
     * @param AbstractModel $object
     * @return $this
     */
    protected function _afterSave(AbstractModel $object)
    {
        $this->refreshPath($object);

        return parent::_afterSave($object);
    }

    /**
     * Recalculate this company's path and cascade the change to its subtree.
     *
     * @param AbstractModel $object
     * @return void
     */
    private function refreshPath(AbstractModel $object): void
    {
        $companyId = (int)$object->getId();
        $parentId = $object->getData(CompanyInterface::PARENT_ID);
        $parentPath = $parentId ? $this->getPathById((int)$parentId) : null;

        $newPath = $parentPath === null
            ? (string)$companyId
            : $parentPath . CompanyInterface::PATH_SEPARATOR . $companyId;
        $oldPath = (string)$object->getData(CompanyInterface::PATH);

        if ($newPath === $oldPath) {
            return;
        }

        $connection = $this->getConnection();
        $newLevel = substr_count($newPath, CompanyInterface::PATH_SEPARATOR);

        $connection->update(
            $this->getMainTable(),
            [CompanyInterface::PATH => $newPath, CompanyInterface::LEVEL => $newLevel],
            [CompanyInterface::ENTITY_ID . ' = ?' => $companyId]
        );
        $object->setData(CompanyInterface::PATH, $newPath);
        $object->setData(CompanyInterface::LEVEL, $newLevel);

        if ($oldPath === '') {
            return;
        }

        // Re-root the subtree: swap the old prefix for the new one and shift every level by the same delta.
        $levelDelta = $newLevel - substr_count($oldPath, CompanyInterface::PATH_SEPARATOR);
        $connection->update(
            $this->getMainTable(),
            [
                CompanyInterface::PATH => new \Zend_Db_Expr(
                    sprintf(
                        'CONCAT(%s, SUBSTRING(path, %d))',
                        $connection->quote($newPath),
                        strlen($oldPath) + 1
                    )
                ),
                CompanyInterface::LEVEL => new \Zend_Db_Expr(sprintf('level + (%d)', $levelDelta)),
            ],
            ['path LIKE ?' => $oldPath . CompanyInterface::PATH_SEPARATOR . '%']
        );
    }

    /**
     * Path of a single company, or NULL when it has none yet / does not exist.
     */
    public function getPathById(int $companyId): ?string
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), CompanyInterface::PATH)
            ->where(CompanyInterface::ENTITY_ID . ' = ?', $companyId);

        $path = $connection->fetchOne($select);

        return $path === false || $path === null || $path === '' ? null : (string)$path;
    }

    /**
     * IDs of a company and everything below it.
     *
     * @return int[]
     */
    public function getSubtreeIds(int $companyId): array
    {
        return array_keys($this->getSubtree($companyId)) ?: [$companyId];
    }

    /**
     * payment_mode of each company on the path, keyed by ID, in the order given.
     *
     * @param int[] $pathIds
     * @return array<int, int>
     */
    public function getPaymentModesByPath(array $pathIds): array
    {
        if (!$pathIds) {
            return [];
        }

        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), [CompanyInterface::ENTITY_ID, CompanyInterface::PAYMENT_MODE])
            ->where(CompanyInterface::ENTITY_ID . ' IN (?)', $pathIds);

        $rows = $connection->fetchPairs($select);
        $ordered = [];
        foreach ($pathIds as $pathId) {
            if (array_key_exists($pathId, $rows)) {
                $ordered[$pathId] = (int)$rows[$pathId];
            }
        }

        return $ordered;
    }

    /**
     * Status of a single company, or NULL when it does not exist.
     */
    public function getStatusById(int $companyId): ?int
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), CompanyInterface::STATUS)
            ->where(CompanyInterface::ENTITY_ID . ' = ?', $companyId);

        $status = $connection->fetchOne($select);

        return $status === false || $status === null ? null : (int)$status;
    }

    /**
     * A company and everything below it: path and status, keyed by company ID.
     *
     * Status comes along because only an active company may confer its customer group, and the
     * caller would otherwise need a second query per company to find that out.
     *
     * @return array<int, array{path: string, status: int}>
     */
    public function getSubtree(int $companyId): array
    {
        $path = $this->getPathById($companyId);
        if ($path === null) {
            return [];
        }

        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(
                $this->getMainTable(),
                [CompanyInterface::ENTITY_ID, CompanyInterface::PATH, CompanyInterface::STATUS]
            )
            ->where(
                'path = ? OR path LIKE ' . $connection->quote($path . CompanyInterface::PATH_SEPARATOR . '%'),
                $path
            );

        $subtree = [];
        foreach ($connection->fetchAll($select) as $row) {
            $subtree[(int)$row[CompanyInterface::ENTITY_ID]] = [
                'path' => (string)$row[CompanyInterface::PATH],
                'status' => (int)$row[CompanyInterface::STATUS],
            ];
        }

        return $subtree;
    }

    /**
     * Whether the company has children (blocks deletion).
     */
    public function hasChildren(int $companyId): bool
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), new \Zend_Db_Expr('1'))
            ->where(CompanyInterface::PARENT_ID . ' = ?', $companyId)
            ->limit(1);

        return (bool)$connection->fetchOne($select);
    }

    /**
     * customer_group_id of each company on the path, keyed by ID, root first.
     *
     * One query for the whole ancestor chain — the input is the company's own path, so the IDs are
     * already known and no recursion is needed.
     *
     * @param int[] $pathIds
     * @return array<int, int|null>
     */
    public function getGroupIdsByPath(array $pathIds): array
    {
        if (!$pathIds) {
            return [];
        }

        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), [CompanyInterface::ENTITY_ID, CompanyInterface::CUSTOMER_GROUP_ID])
            ->where(CompanyInterface::ENTITY_ID . ' IN (?)', $pathIds);

        $rows = $connection->fetchPairs($select);
        $ordered = [];
        foreach ($pathIds as $pathId) {
            if (array_key_exists($pathId, $rows)) {
                $ordered[$pathId] = $rows[$pathId] === null ? null : (int)$rows[$pathId];
            }
        }

        return $ordered;
    }

    /**
     * Options for a parent-company select, excluding a company's own subtree so a cycle cannot be
     * picked in the first place.
     *
     * @return array<int, string>
     */
    public function getCompanyOptions(?int $excludeSubtreeOf = null): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), [CompanyInterface::ENTITY_ID, CompanyInterface::NAME])
            ->order(CompanyInterface::NAME . ' ' . Select::SQL_ASC);

        if ($excludeSubtreeOf !== null) {
            $excluded = $this->getSubtreeIds($excludeSubtreeOf);
            $select->where(CompanyInterface::ENTITY_ID . ' NOT IN (?)', $excluded);
        }

        return array_map('strval', $connection->fetchPairs($select));
    }
}
