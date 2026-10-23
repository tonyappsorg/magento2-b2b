<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model\ResourceModel\Company\Grid;

use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

/**
 * Grid (UI listing) collection for companies.
 *
 * mainTable / resourceModel are supplied via di.xml so this stays a thin SearchResult.
 */
class Collection extends SearchResult implements SearchResultInterface
{
}
