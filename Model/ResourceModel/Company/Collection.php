<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model\ResourceModel\Company;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Model\Company as CompanyModel;
use Tonyapps\B2b\Model\ResourceModel\Company as CompanyResource;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = CompanyInterface::ENTITY_ID;

    protected function _construct()
    {
        $this->_init(CompanyModel::class, CompanyResource::class);
    }

}
