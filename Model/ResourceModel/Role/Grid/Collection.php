<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model\ResourceModel\Role\Grid;

use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface as FetchStrategy;
use Magento\Framework\Data\Collection\EntityFactoryInterface as EntityFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Psr\Log\LoggerInterface as Logger;
use Tonyapps\B2b\Api\Data\RoleInterface;

/**
 * Grid collection for the admin roles listing.
 *
 * Roles only mean something inside a company, so the grid is always scoped to the company in the
 * URL. Without the parameter it lists every role, which is a useful overview but never linked to.
 */
class Collection extends SearchResult implements SearchResultInterface
{
    public function __construct(
        EntityFactory $entityFactory,
        Logger $logger,
        FetchStrategy $fetchStrategy,
        EventManager $eventManager,
        private readonly RequestInterface $request,
        $mainTable = 'tonyapps_b2b_role',
        $resourceModel = \Tonyapps\B2b\Model\ResourceModel\Role::class,
        $identifierName = null,
        $connectionName = null
    ) {
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $mainTable,
            $resourceModel,
            $identifierName,
            $connectionName
        );
    }

    /**
     * @return $this
     */
    protected function _renderFiltersBefore()
    {
        $companyId = (int)$this->request->getParam(RoleInterface::COMPANY_ID);
        if ($companyId) {
            $this->getSelect()->where('main_table.company_id = ?', $companyId);
        }

        parent::_renderFiltersBefore();

        return $this;
    }
}
