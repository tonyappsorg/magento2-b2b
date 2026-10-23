<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model\ResourceModel\Role;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Tonyapps\B2b\Api\Data\RoleInterface;
use Tonyapps\B2b\Model\ResourceModel\Role as RoleResource;
use Tonyapps\B2b\Model\Role as RoleModel;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = RoleInterface::ROLE_ID;

    protected function _construct()
    {
        $this->_init(RoleModel::class, RoleResource::class);
    }

    /**
     * @return $this
     */
    public function addCompanyFilter(int $companyId): self
    {
        $this->addFieldToFilter(RoleInterface::COMPANY_ID, $companyId);
        $this->setOrder(RoleInterface::ROLE_NAME, self::SORT_ORDER_ASC);

        return $this;
    }
}
