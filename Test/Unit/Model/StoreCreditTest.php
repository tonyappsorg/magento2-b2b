<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Model\ResourceModel\Company as CompanyResource;
use Tonyapps\B2b\Model\ResourceModel\CreditHistory;
use Tonyapps\B2b\Model\StoreCredit;

/**
 * The money path: what is written, and what is refused.
 */
class StoreCreditTest extends TestCase
{
    private AdapterInterface&MockObject $connection;
    private CreditHistory&MockObject $creditHistory;
    private StoreCredit $storeCredit;

    private float $balance = 0.0;
    private bool $locked = false;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $inserts = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $updates = [];
    private int $commits = 0;
    private int $rollbacks = 0;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        // The row lock is the whole point of this class; record that it was asked for.
        $select->method('forUpdate')->willReturnCallback(function (bool $flag) use ($select): Select {
            $this->locked = $flag;

            return $select;
        });
        $this->connection->method('select')->willReturn($select);
        $this->connection->method('fetchOne')->willReturnCallback(fn() => $this->balance);
        $this->connection->method('update')->willReturnCallback(
            function (string $table, array $bind): int {
                $this->updates[] = $bind;
                if (array_key_exists('credit_balance', $bind)) {
                    $this->balance = (float)$bind['credit_balance'];
                }

                return 1;
            }
        );
        $this->connection->method('insert')->willReturnCallback(
            function (string $table, array $row): void {
                $this->inserts[] = $row;
            }
        );
        $this->connection->method('beginTransaction')->willReturnSelf();
        $this->connection->method('commit')->willReturnCallback(function () { $this->commits++; });
        $this->connection->method('rollBack')->willReturnCallback(function () { $this->rollbacks++; });

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);

        $companyResource = $this->createMock(CompanyResource::class);
        $companyResource->method('getMainTable')->willReturn('tonyapps_b2b_company');

        $this->creditHistory = $this->createMock(CreditHistory::class);
        $this->creditHistory->method('getMainTable')->willReturn('tonyapps_b2b_credit_history');

        // Company 9 sits under root 1, so everything resolves to the root's pool.
        $company = $this->createMock(CompanyInterface::class);
        $company->method('getRootId')->willReturn(1);
        $companyRepository = $this->createMock(CompanyRepositoryInterface::class);
        $companyRepository->method('getById')->willReturn($company);

        $this->storeCredit = new StoreCredit(
            $resourceConnection,
            $companyResource,
            $this->creditHistory,
            $companyRepository
        );
    }

    public function testSpendingIsTakenFromTheRootCompanysPool(): void
    {
        $this->balance = 100.0;

        $balance = $this->storeCredit->debit(9, 30.0, 555, '000000555', 7);

        self::assertSame(70.0, $balance);
        self::assertSame(1, $this->commits);
        // Charged to the root, not to the company that placed the order.
        self::assertSame(1, $this->inserts[0]['company_id']);
        self::assertSame(-30.0, $this->inserts[0]['delta']);
        self::assertSame(70.0, $this->inserts[0]['balance_after']);
        self::assertSame(StoreCredit::TYPE_ORDER, $this->inserts[0]['type']);
        self::assertSame(555, $this->inserts[0]['order_id']);
    }

    public function testTheBalanceIsReadUnderARowLock(): void
    {
        $this->balance = 10.0;
        $this->storeCredit->debit(9, 5.0, 1, '1', 7);

        self::assertTrue($this->locked, 'the pool row must be locked before the balance is read');
    }

    public function testSpendingMoreThanThereIsIsRefusedAndWritesNothing(): void
    {
        $this->balance = 20.0;

        try {
            $this->storeCredit->debit(9, 20.01, 1, '1', 7);
            self::fail('a debit beyond the balance should have been refused');
        } catch (LocalizedException) {
            // expected
        }

        self::assertSame([], $this->inserts);
        self::assertSame(1, $this->rollbacks);
        self::assertSame(0, $this->commits);
    }

    public function testSpendingExactlyTheWholeBalanceIsAllowed(): void
    {
        $this->balance = 25.0;

        self::assertSame(0.0, $this->storeCredit->debit(9, 25.0, 1, '1', 7));
    }

    public function testAnAdjustmentCanTakeTheBalanceNegative(): void
    {
        // Deliberate: an administrator correcting an over-credit is not the same as a customer
        // overspending, so only debits are guarded.
        $this->balance = 10.0;

        self::assertSame(-15.0, $this->storeCredit->adjust(9, -25.0, 'correction', 3));
        self::assertSame(StoreCredit::TYPE_ADJUST, $this->inserts[0]['type']);
        self::assertSame(3, $this->inserts[0]['admin_user_id']);
    }

    public function testAnEmptyAdjustmentIsRefused(): void
    {
        $this->expectException(LocalizedException::class);
        $this->storeCredit->adjust(9, 0.0, 'nothing');
    }

    public function testARefundAddsTheMoneyBack(): void
    {
        $this->balance = 5.0;

        self::assertSame(35.0, $this->storeCredit->refund(9, 30.0, 555, '000000555', 'Refund'));
        self::assertSame(StoreCredit::TYPE_REFUND, $this->inserts[0]['type']);
    }

    public function testCancellingGivesBackExactlyWhatTheOrderTook(): void
    {
        $this->balance = 70.0;
        // Ledger says 30 was spent on this order and nothing given back yet.
        $this->creditHistory->method('getNetForOrder')->with(555)->willReturn(-30.0);

        self::assertSame(100.0, $this->storeCredit->revertOrder(9, 555, '000000555'));
        self::assertSame(StoreCredit::TYPE_REVERT, $this->inserts[0]['type']);
    }

    public function testCancellingAnOrderAlreadyGivenBackDoesNothing(): void
    {
        $this->creditHistory->method('getNetForOrder')->willReturn(0.0);

        self::assertNull($this->storeCredit->revertOrder(9, 555, '000000555'));
        self::assertSame([], $this->inserts);
    }

    public function testCancellingAfterAPartialRefundGivesBackOnlyTheRest(): void
    {
        $this->balance = 80.0;
        // Spent 30, already refunded 10: 20 still outstanding.
        $this->creditHistory->method('getNetForOrder')->willReturn(-20.0);

        self::assertSame(100.0, $this->storeCredit->revertOrder(9, 555, '000000555'));
    }

    public function testCanCoverComparesAgainstTheBalance(): void
    {
        $this->balance = 50.0;

        self::assertTrue($this->storeCredit->canCover(9, 50.0));
        self::assertTrue($this->storeCredit->canCover(9, 49.99));
        self::assertFalse($this->storeCredit->canCover(9, 50.01));
    }

    public function testADebitOfNothingIsRefused(): void
    {
        $this->expectException(LocalizedException::class);
        $this->storeCredit->debit(9, 0.0, 1, '1', 7);
    }
}
