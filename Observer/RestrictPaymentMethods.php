<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Observer;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Tonyapps\B2b\Model\PaymentRestriction;

/**
 * Hides payment methods a company is not allowed to use.
 *
 * The customer comes from the quote where there is one — which covers checkout and an order an
 * administrator places on a customer's behalf — and from the session otherwise.
 */
class RestrictPaymentMethods implements ObserverInterface
{
    public function __construct(
        private readonly PaymentRestriction $paymentRestriction,
        private readonly CustomerSession $customerSession
    ) {
    }

    public function execute(Observer $observer): void
    {
        $result = $observer->getEvent()->getData('result');
        $methodInstance = $observer->getEvent()->getData('method_instance');
        if (!$result || !$methodInstance || $result->getData('is_available') === false) {
            return;
        }

        $customerId = $this->getCustomerId($observer);
        if ($customerId === null) {
            return;
        }

        if (!$this->paymentRestriction->isMethodAllowed($customerId, (string)$methodInstance->getCode())) {
            $result->setData('is_available', false);
        }
    }

    private function getCustomerId(Observer $observer): ?int
    {
        $quote = $observer->getEvent()->getData('quote');
        if ($quote && $quote->getCustomerId()) {
            return (int)$quote->getCustomerId();
        }

        // No quote on the event: only the storefront session can say who is asking, and a guest
        // belongs to no company.
        $customerId = (int)$this->customerSession->getCustomerId();

        return $customerId ?: null;
    }
}
