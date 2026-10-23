<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * The payment methods a company is allowed to use.
 *
 * Only consulted when the company's payment_mode is "restrict"; an empty list under that mode
 * means the company may use nothing, which is a deliberate configuration rather than a bug.
 */
class CompanyPayment extends AbstractDb
{
    public const TABLE = 'tonyapps_b2b_company_payment';

    protected function _construct()
    {
        $this->_init(self::TABLE, 'company_id');
    }

    /**
     * @return string[]
     */
    public function getMethodCodes(int $companyId): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), 'method_code')
            ->where('company_id = ?', $companyId);

        return array_map('strval', $connection->fetchCol($select));
    }

    /**
     * Replace a company's allowed list.
     *
     * @param string[] $methodCodes
     */
    public function setMethodCodes(int $companyId, array $methodCodes): void
    {
        $connection = $this->getConnection();
        $connection->delete($this->getMainTable(), ['company_id = ?' => $companyId]);

        $methodCodes = array_values(array_unique(array_filter(array_map('strval', $methodCodes))));
        if (!$methodCodes) {
            return;
        }

        $connection->insertMultiple(
            $this->getMainTable(),
            array_map(
                static fn(string $code): array => ['company_id' => $companyId, 'method_code' => $code],
                $methodCodes
            )
        );
    }
}
