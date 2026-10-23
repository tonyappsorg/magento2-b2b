<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Tonyapps\B2b\Api\MembershipManagementInterface;

/**
 * Records which company an order was placed for.
 *
 * Stamped once, at checkout, rather than derived later from the customer's membership: a buyer who
 * moves to another company must not drag their old orders into the new company's view, nor
 * disappear from the old one's history.
 */
class StampCompanyOnOrder implements ObserverInterface
{
    public function __construct(
        private readonly MembershipManagementInterface $membership
    ) {
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getData('order');
        if (!$order || $order->getData('company_id')) {
            return;
        }

        $customerId = (int)$order->getCustomerId();
        if (!$customerId) {
            return;
        }

        $companyId = $this->membership->getCompanyId($customerId);
        if ($companyId !== null) {
            $order->setData('company_id', $companyId);
        }
    }
}
