<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\OrderFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tonyapps\B2b\Model\Payment\StoreCreditFacade;
use Tonyapps\B2b\Model\StoreCredit;
use Tonyapps\B2b\Observer\RevertStoreCreditOnFailedOrder;

/**
 * A failed submit must not leave the debit behind, or the retry under the same increment ID pays twice.
 */
class RevertStoreCreditOnFailedOrderTest extends TestCase
{
    private StoreCredit&MockObject $storeCredit;
    private ?int $savedOrderId = null;
    private RevertStoreCreditOnFailedOrder $observer;

    protected function setUp(): void
    {
        $this->storeCredit = $this->createMock(StoreCredit::class);
        $orderFactory = $this->createMock(OrderFactory::class);
        $orderFactory->method('create')->willReturnCallback(function (): Order {
            $saved = $this->createMock(Order::class);
            $saved->method('loadByIncrementIdAndStoreId')->with('000000555', 1)->willReturnSelf();
            $saved->method('getId')->willReturn($this->savedOrderId);

            return $saved;
        });

        $this->observer = new RevertStoreCreditOnFailedOrder(
            $this->storeCredit,
            $orderFactory,
            $this->createMock(LoggerInterface::class)
        );
    }

    private function observe(string $method = StoreCreditFacade::CODE): void
    {
        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn($method);
        $order = $this->createMock(Order::class);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getData')->with('company_id')->willReturn(9);
        $order->method('getIncrementId')->willReturn('000000555');
        $order->method('getStoreId')->willReturn(1);
        // A rolled-back insert leaves its ID on the object; it must not count as saved.
        $order->method('getId')->willReturn(42);

        $this->observer->execute(new Observer(['event' => new Event(['order' => $order])]));
    }

    public function testAnUnsavedOrderGivesBackWhatTheLedgerHoldsForItsIncrementId(): void
    {
        $this->storeCredit->expects(self::once())->method('revertOrder')
            ->with(9, 0, '000000555', PHP_FLOAT_MAX, self::isType('string'));

        $this->observe();
    }

    public function testAnOrderThatWasSavedKeepsItsDebit(): void
    {
        $this->savedOrderId = 42;
        $this->storeCredit->expects(self::never())->method('revertOrder');

        $this->observe();
    }

    public function testOtherPaymentMethodsAreIgnored(): void
    {
        $this->storeCredit->expects(self::never())->method('revertOrder');

        $this->observe('checkmo');
    }

    public function testALedgerFailureDoesNotReplaceTheCheckoutError(): void
    {
        $this->storeCredit->method('revertOrder')->willThrowException(new \RuntimeException('db down'));

        $this->observe();
        $this->addToAssertionCount(1);
    }
}
