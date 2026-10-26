<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use Tonyapps\B2b\Model\Payment\StoreCreditFacade;
use Tonyapps\B2b\Model\StoreCredit;

/**
 * Gives back what a cancelled order took.
 *
 * The amount comes from the ledger, not the order total, so a partly refunded order gives back
 * only what is still outstanding and a second cancel gives back nothing.
 */
class RevertStoreCreditOnCancel implements ObserverInterface
{
    public function __construct(
        private readonly StoreCredit $storeCredit,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getData('order');
        if (!$order || (string)$order->getPayment()?->getMethod() !== StoreCreditFacade::CODE) {
            return;
        }

        $companyId = (int)$order->getData('company_id');
        if (!$companyId || !$order->getId()) {
            return;
        }

        try {
            $this->storeCredit->revertOrder(
                $companyId,
                (int)$order->getId(),
                (string)$order->getIncrementId(),
                (float)$order->getBaseTotalCanceled()
            );
        } catch (\Exception $exception) {
            $this->logger->error(
                sprintf(
                    'Tonyapps_B2b could not return store credit for cancelled order %s: %s',
                    $order->getIncrementId(),
                    $exception->getMessage()
                )
            );
        }
    }
}
