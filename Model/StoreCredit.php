<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Model\ResourceModel\Company as CompanyResource;
use Tonyapps\B2b\Model\ResourceModel\CreditHistory;

/**
 * The store credit ledger.
 *
 * One pool per **root** company: every descendant spends from the same balance, which is what
 * "full roll-up" means. The balance column on any non-root company row is ignored.
 *
 * Every movement goes through applyDelta(), which takes a row lock on the root company before it
 * reads the balance. Without that lock two checkouts finishing at the same moment would both read
 * the old balance and both think they could afford the order, and the company would spend the same
 * money twice.
 */
class StoreCredit
{
    public const TYPE_ADJUST = 'adjust';
    public const TYPE_ORDER = 'order';
    public const TYPE_REFUND = 'refund';
    public const TYPE_REVERT = 'revert';

    /**
     * Money is compared to four decimal places, matching the column; anything smaller is rounding
     * noise, not a shortfall.
     */
    private const EPSILON = 0.00005;

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly CompanyResource $companyResource,
        private readonly CreditHistory $creditHistory,
        private readonly CompanyRepositoryInterface $companyRepository
    ) {
    }

    /**
     * The company that owns the pool this one spends from: its root.
     */
    public function getPoolCompanyId(int $companyId): int
    {
        try {
            $rootId = $this->companyRepository->getById($companyId)->getRootId();
        } catch (NoSuchEntityException) {
            return $companyId;
        }

        return $rootId ?? $companyId;
    }

    public function getBalance(int $companyId): float
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->companyResource->getMainTable(), CompanyInterface::CREDIT_BALANCE)
            ->where(CompanyInterface::ENTITY_ID . ' = ?', $this->getPoolCompanyId($companyId));

        return (float)$connection->fetchOne($select);
    }

    public function canCover(int $companyId, float $amount): bool
    {
        return $this->getBalance($companyId) + self::EPSILON >= $amount;
    }

    /**
     * An administrator adding or removing credit by hand.
     *
     * @throws LocalizedException
     */
    public function adjust(int $companyId, float $delta, string $comment, ?int $adminUserId = null): float
    {
        if (abs($delta) < self::EPSILON) {
            throw new LocalizedException(__('Enter an amount to add or remove.'));
        }

        return $this->applyDelta($companyId, $delta, self::TYPE_ADJUST, [
            'comment' => $comment,
            'admin_user_id' => $adminUserId,
        ]);
    }

    /**
     * Spend credit on an order. Refuses rather than letting the balance go negative.
     *
     * @throws LocalizedException
     */
    public function debit(int $companyId, float $amount, int $orderId, ?string $incrementId, ?int $customerId): float
    {
        if ($amount < self::EPSILON) {
            throw new LocalizedException(__('There is nothing to pay with store credit.'));
        }

        return $this->applyDelta($companyId, -$amount, self::TYPE_ORDER, [
            'order_id' => $orderId ?: null,
            'order_increment_id' => $incrementId,
            'customer_id' => $customerId,
        ], true);
    }

    /**
     * Give credit back for a refunded order.
     */
    public function refund(int $companyId, float $amount, int $orderId, ?string $incrementId, string $comment): float
    {
        return $this->applyDelta($companyId, abs($amount), self::TYPE_REFUND, [
            'order_id' => $orderId ?: null,
            'order_increment_id' => $incrementId,
            'comment' => $comment,
        ]);
    }

    /**
     * Give back what an order took, when the order is cancelled.
     *
     * Works from the ledger rather than the order total: only what was actually spent on this
     * order comes back, and only once however many times a cancel is triggered. Capped at
     * $canceledAmount so a partly invoiced order keeps paying for what was invoiced.
     */
    public function revertOrder(
        int $companyId,
        int $orderId,
        string $incrementId,
        float $canceledAmount,
        ?string $comment = null
    ): ?float {
        $spent = -$this->creditHistory->getNetForOrder($this->getPoolCompanyId($companyId), $incrementId);
        $spent = min($spent, $canceledAmount);
        if ($spent < self::EPSILON) {
            return null;
        }

        return $this->applyDelta($companyId, $spent, self::TYPE_REVERT, [
            'order_id' => $orderId ?: null,
            'order_increment_id' => $incrementId,
            'comment' => $comment ?? (string)__('Order cancelled'),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(int $companyId, int $limit = 50): array
    {
        return $this->creditHistory->getHistory($this->getPoolCompanyId($companyId), $limit);
    }

    /**
     * Move the balance and record why, under a row lock on the pool.
     *
     * ponytail: the lock makes concurrent checkouts safe against each other, but the debit is
     * committed before Magento saves the order row — Magento does not wrap order placement in a
     * transaction we can join. If that save then fails, RevertStoreCreditOnFailedOrder gives the
     * spend back on sales_model_service_quote_submit_failure; only a crash between the two leaves
     * a spend with no order, traceable by its order_increment_id and correctable with an adjustment.
     *
     * @param array<string, mixed> $entry
     * @throws LocalizedException
     */
    private function applyDelta(
        int $companyId,
        float $delta,
        string $type,
        array $entry = [],
        bool $requireFunds = false
    ): float {
        $poolId = $this->getPoolCompanyId($companyId);
        $connection = $this->resourceConnection->getConnection();
        $table = $this->companyResource->getMainTable();

        $connection->beginTransaction();

        try {
            // FOR UPDATE: nobody else reads this balance until the movement is committed.
            $select = $connection->select()
                ->from($table, CompanyInterface::CREDIT_BALANCE)
                ->where(CompanyInterface::ENTITY_ID . ' = ?', $poolId)
                ->forUpdate(true);
            $balance = $connection->fetchOne($select);

            if ($balance === false) {
                throw new LocalizedException(__('That company no longer exists.'));
            }

            $balance = (float)$balance;
            $newBalance = $balance + $delta;

            if ($requireFunds && $newBalance < -self::EPSILON) {
                throw new LocalizedException(
                    __('There is not enough store credit left to pay for this order.')
                );
            }

            $newBalance = round($newBalance, 4);

            $connection->update(
                $table,
                [CompanyInterface::CREDIT_BALANCE => $newBalance],
                [CompanyInterface::ENTITY_ID . ' = ?' => $poolId]
            );

            $connection->insert($this->creditHistory->getMainTable(), array_merge([
                'company_id' => $poolId,
                'delta' => round($delta, 4),
                'balance_after' => $newBalance,
                'type' => $type,
            ], array_filter($entry, static fn($value): bool => $value !== null)));

            $connection->commit();

            return $newBalance;
        } catch (\Exception $exception) {
            $connection->rollBack();

            throw $exception instanceof LocalizedException
                ? $exception
                : new LocalizedException(__('Could not update the store credit: %1', $exception->getMessage()));
        }
    }
}
