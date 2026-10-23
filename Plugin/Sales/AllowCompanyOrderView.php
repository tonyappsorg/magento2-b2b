<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Plugin\Sales;

use Magento\Sales\Controller\AbstractController\OrderViewAuthorization;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config as OrderConfig;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2b\Model\Permissions;
use Tonyapps\B2b\Model\ResourceModel\Company as CompanyResource;

/**
 * Lets someone with the company-orders permission open an order placed by a colleague.
 *
 * Without this the Company Orders list would be a dead end: every row belonging to somebody else
 * would 404 on the core ownership check. Only widens access — the core answer is returned
 * untouched whenever it already says yes.
 */
class AllowCompanyOrderView
{
    public function __construct(
        private readonly CompanyContext $companyContext,
        private readonly CompanyResource $companyResource,
        private readonly OrderConfig $orderConfig
    ) {
    }

    /**
     * @param OrderViewAuthorization $subject
     * @param bool $result
     * @param Order $order
     * @return bool
     */
    public function afterCanView(OrderViewAuthorization $subject, $result, Order $order): bool
    {
        if ($result) {
            return true;
        }

        if (!$this->companyContext->isAllowed(Permissions::ORDERS_VIEW_COMPANY)) {
            return false;
        }

        $company = $this->companyContext->getCompany();
        $orderCompanyId = (int)$order->getData('company_id');
        if ($company === null || !$orderCompanyId) {
            return false;
        }

        // Only orders from this company or one below it, and only states a customer may see —
        // the permission widens whose orders are visible, never which states are.
        if (!in_array($orderCompanyId, $this->companyResource->getSubtreeIds((int)$company->getId()), true)) {
            return false;
        }

        return in_array($order->getStatus(), $this->orderConfig->getVisibleOnFrontStatuses(), true);
    }
}
