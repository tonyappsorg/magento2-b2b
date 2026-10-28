<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Block\Company;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config as OrderConfig;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Theme\Block\Html\Pager;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2b\Model\ResourceModel\Company as CompanyResource;

/**
 * Orders placed across the company's whole subtree.
 */
class Orders extends Template
{
    private const PAGE_SIZE = 20;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Collection|null
     */
    private $orders = null;

    public function __construct(
        Context $context,
        private readonly CompanyContext $companyContext,
        private readonly CompanyResource $companyResource,
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly OrderConfig $orderConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return \Magento\Sales\Model\ResourceModel\Order\Collection
     */
    public function getOrders()
    {
        if ($this->orders !== null) {
            return $this->orders;
        }

        $collection = $this->orderCollectionFactory->create();
        $company = $this->companyContext->getCompany();

        if ($company === null) {
            // An impossible filter is safer than returning everything.
            $collection->addFieldToFilter('entity_id', ['null' => true]);

            return $this->orders = $collection;
        }

        $collection
            ->addFieldToSelect(['increment_id', 'created_at', 'status', 'grand_total', 'customer_id', 'company_id'])
            ->addFieldToFilter('company_id', ['in' => $this->companyResource->getSubtreeIds((int)$company->getId())])
            // The same rule as core's own order history and AllowCompanyOrderView: statuses marked visible on
            // the storefront. Filtering out state "new" hid every store-credit order, which stays "new".
            ->addFieldToFilter('status', ['in' => $this->orderConfig->getVisibleOnFrontStatuses()])
            ->setOrder('created_at', 'desc');

        $collection->setPageSize(self::PAGE_SIZE)
            ->setCurPage(max(1, (int)$this->getRequest()->getParam('p', 1)));

        return $this->orders = $collection;
    }

    /**
     * Company names for the subtree, so each row can say which company it belongs to.
     *
     * @return array<int, string>
     */
    public function getCompanyNames(): array
    {
        $company = $this->companyContext->getCompany();

        return $company === null ? [] : $this->companyResource->getCompanyOptions();
    }

    public function getViewUrl(Order $order): string
    {
        return $this->getUrl('sales/order/view', ['order_id' => $order->getId()]);
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('b2b/company/index');
    }

    /**
     * @return $this
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        /** @var Pager $pager */
        $pager = $this->getLayout()->createBlock(Pager::class, 'tonyapps_b2b.company.orders.pager');
        $pager->setLimit(self::PAGE_SIZE)->setCollection($this->getOrders());
        $this->setChild('pager', $pager);

        return $this;
    }

    public function getPagerHtml(): string
    {
        return $this->getChildHtml('pager');
    }
}
