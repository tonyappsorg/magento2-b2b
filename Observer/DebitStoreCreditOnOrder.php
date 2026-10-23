<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Tonyapps\B2b\Api\MembershipManagementInterface;
use Tonyapps\B2b\Model\Payment\StoreCreditFacade;
use Tonyapps\B2b\Model\StoreCredit;

/**
 * Takes the order total out of the company's credit when the order is placed with it.
 *
 * Runs on sales_order_place_after, which Magento dispatches *before* it saves the order row — so
 * throwing here stops the order rather than leaving an unpayable one behind.
 */
class DebitStoreCreditOnOrder implements ObserverInterface
{
    public function __construct(
        private readonly StoreCredit $storeCredit,
        private readonly MembershipManagementInterface $membership
    ) {
    }

    /**
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getData('order');
        if (!$order || (string)$order->getPayment()?->getMethod() !== StoreCreditFacade::CODE) {
            return;
        }

        $customerId = (int)$order->getCustomerId();
        $companyId = $customerId ? $this->membership->getCompanyId($customerId) : null;

        if ($companyId === null) {
            throw new LocalizedException(
                __('Store credit can only be used by a company account.')
            );
        }

        // Base currency: the ledger holds one currency, the store's base.
        $this->storeCredit->debit(
            $companyId,
            (float)$order->getBaseGrandTotal(),
            (int)$order->getId(),
            (string)$order->getIncrementId(),
            $customerId
        );
    }
}
