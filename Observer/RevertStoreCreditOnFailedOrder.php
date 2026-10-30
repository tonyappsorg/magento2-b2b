<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\OrderFactory;
use Psr\Log\LoggerInterface;
use Tonyapps\B2b\Model\Payment\StoreCreditFacade;
use Tonyapps\B2b\Model\StoreCredit;

/**
 * Gives back a debit whose order was never saved.
 *
 * The debit is committed on sales_order_place_after, before Magento writes the order row. When that
 * write (or anything after it in the submit) fails, the quote keeps its reserved increment ID, so
 * the retry would debit the same increment ID a second time. Reverting the ledger's net for that
 * increment ID puts it back to zero. An order that did get saved keeps its debit: cancelling it
 * gives the money back the usual way.
 *
 * Never throws: an exception here would replace the original checkout error.
 */
class RevertStoreCreditOnFailedOrder implements ObserverInterface
{
    public function __construct(
        private readonly StoreCredit $storeCredit,
        private readonly OrderFactory $orderFactory,
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
        $incrementId = (string)$order->getIncrementId();
        if (!$companyId || $incrementId === '') {
            return;
        }

        try {
            // The in-memory order keeps the ID of a rolled-back insert, so ask the database.
            $saved = $this->orderFactory->create()
                ->loadByIncrementIdAndStoreId($incrementId, (int)$order->getStoreId());
            if ($saved->getId()) {
                return;
            }

            $this->storeCredit->revertOrder(
                $companyId,
                0,
                $incrementId,
                PHP_FLOAT_MAX,
                (string)__('Order could not be placed')
            );
        } catch (\Exception $exception) {
            $this->logger->error(
                sprintf(
                    'Tonyapps_B2b could not return store credit for failed order %s: %s',
                    $incrementId,
                    $exception->getMessage()
                )
            );
        }
    }
}
