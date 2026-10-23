<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * The store credit ledger.
 */
class CreditHistory extends AbstractDb
{
    public const TABLE = 'tonyapps_b2b_credit_history';

    protected function _construct()
    {
        $this->_init(self::TABLE, 'entity_id');
    }

    /**
     * Recent entries for a company, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(int $companyId, int $limit = 50): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('company_id = ?', $companyId)
            ->order('entity_id DESC')
            ->limit($limit);

        return $connection->fetchAll($select);
    }

    /**
     * Net movement recorded against one order.
     *
     * A debit is negative and a refund positive, so a non-negative total means everything spent on
     * that order has already been given back — the guard against refunding twice.
     */
    public function getNetForOrder(int $orderId): float
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), new \Zend_Db_Expr('COALESCE(SUM(delta), 0)'))
            ->where('order_id = ?', $orderId);

        return (float)$connection->fetchOne($select);
    }
}
