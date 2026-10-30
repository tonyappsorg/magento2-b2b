<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tonyapps\B2b\Model\Payment\StoreCreditFacade;
use Tonyapps\B2b\Model\StoreCredit;
use Tonyapps\B2b\Observer\RefundStoreCredit;

/**
 * The ledger moves only after the credit memo is committed, and only once per refund.
 */
class RefundStoreCreditTest extends TestCase
{
    private StoreCredit&MockObject $storeCredit;
    private RefundStoreCredit $observer;
    private Creditmemo $creditmemo;

    protected function setUp(): void
    {
        $this->storeCredit = $this->createMock(StoreCredit::class);
        $this->observer = new RefundStoreCredit($this->storeCredit, $this->createMock(LoggerInterface::class));

        $payment = $this->createMock(Payment::class);
        $payment->method('getMethod')->willReturn(StoreCreditFacade::CODE);
        $order = $this->createMock(Order::class);
        $order->method('getPayment')->willReturn($payment);
        $order->method('getData')->with('company_id')->willReturn(9);
        $order->method('getId')->willReturn(555);
        $order->method('getIncrementId')->willReturn('000000555');

        // A real data object, so the mark set on one event is seen on the next.
        $this->creditmemo = $this->getMockBuilder(Creditmemo::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOrder', 'getBaseGrandTotal'])
            ->getMock();
        $this->creditmemo->method('getOrder')->willReturn($order);
        $this->creditmemo->method('getBaseGrandTotal')->willReturn(30.0);
    }

    private function dispatch(string $eventName): void
    {
        $event = new Event(['creditmemo' => $this->creditmemo]);
        $event->setName($eventName);
        $this->observer->execute(new Observer(['event' => $event]));
    }

    public function testNothingMovesInsideTheRefundTransaction(): void
    {
        $this->storeCredit->expects(self::never())->method('refund');

        $this->dispatch('sales_order_creditmemo_refund');
    }

    public function testTheRefundIsAppliedOnceAfterCommit(): void
    {
        $this->storeCredit->expects(self::once())->method('refund')
            ->with(9, 30.0, 555, '000000555', self::isType('string'));

        $this->dispatch('sales_order_creditmemo_refund');
        $this->dispatch('sales_order_creditmemo_save_commit_after');
        // A later save of the same credit memo (a comment, an email flag) refunds nothing.
        $this->dispatch('sales_order_creditmemo_save_commit_after');
    }

    public function testASaveThatWasNotARefundMovesNothing(): void
    {
        $this->storeCredit->expects(self::never())->method('refund');

        $this->dispatch('sales_order_creditmemo_save_commit_after');
    }
}
