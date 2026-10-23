<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use Tonyapps\B2b\Model\Payment\StoreCreditFacade;
use Tonyapps\B2b\Model\StoreCredit;

/**
 * Puts money back on the company's credit when an order paid with it is refunded.
 *
 * A failure is logged rather than thrown: the credit memo has already been created by this point,
 * and throwing would leave the refund half-applied. An administrator can correct the balance by
 * hand, and the ledger shows what happened.
 */
class RefundStoreCredit implements ObserverInterface
{
    public function __construct(
        private readonly StoreCredit $storeCredit,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $creditmemo = $observer->getEvent()->getData('creditmemo');
        if (!$creditmemo) {
            return;
        }

        $order = $creditmemo->getOrder();
        if (!$order || (string)$order->getPayment()?->getMethod() !== StoreCreditFacade::CODE) {
            return;
        }

        $companyId = (int)$order->getData('company_id');
        $amount = (float)$creditmemo->getBaseGrandTotal();
        if (!$companyId || $amount <= 0) {
            return;
        }

        try {
            $this->storeCredit->refund(
                $companyId,
                $amount,
                (int)$order->getId(),
                (string)$order->getIncrementId(),
                (string)__('Refund for order %1', $order->getIncrementId())
            );
        } catch (\Exception $exception) {
            $this->logger->error(
                sprintf(
                    'Tonyapps_B2b could not return %s of store credit for order %s: %s',
                    $amount,
                    $order->getIncrementId(),
                    $exception->getMessage()
                )
            );
        }
    }
}
