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
 * Two events: sales_order_creditmemo_refund only marks the credit memo, and the money moves on
 * sales_order_creditmemo_save_commit_after, once CreditmemoService has committed. Writing the ledger
 * inside that transaction meant a failed ledger write rolled back a nested transaction and broke the
 * whole credit memo; the mark makes sure a later save of the same credit memo refunds nothing.
 *
 * A failure is logged rather than thrown: the credit memo is committed by this point. An
 * administrator can correct the balance by hand, and the ledger shows what happened.
 */
class RefundStoreCredit implements ObserverInterface
{
    private const REFUND_EVENT = 'sales_order_creditmemo_refund';
    private const PENDING_FLAG = 'tonyapps_b2b_credit_refund_pending';

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

        if ($observer->getEvent()->getName() === self::REFUND_EVENT) {
            $creditmemo->setData(self::PENDING_FLAG, true);

            return;
        }

        if (!$creditmemo->getData(self::PENDING_FLAG)) {
            return;
        }
        $creditmemo->unsetData(self::PENDING_FLAG);

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
