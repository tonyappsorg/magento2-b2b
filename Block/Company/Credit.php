<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Block\Company;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2b\Model\StoreCredit;

/**
 * Store credit balance and recent movements, for the company's own people.
 */
class Credit extends Template
{
    public function __construct(
        Context $context,
        private readonly CompanyContext $companyContext,
        private readonly StoreCredit $storeCredit,
        private readonly PriceCurrencyInterface $priceCurrency,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getBalance(): float
    {
        $company = $this->companyContext->getCompany();

        return $company === null ? 0.0 : $this->storeCredit->getBalance((int)$company->getId());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(): array
    {
        $company = $this->companyContext->getCompany();

        return $company === null ? [] : $this->storeCredit->getHistory((int)$company->getId(), 50);
    }

    /**
     * Whether this company spends from an ancestor's pool rather than its own.
     */
    public function isSharedPool(): bool
    {
        $company = $this->companyContext->getCompany();

        return $company !== null
            && $this->storeCredit->getPoolCompanyId((int)$company->getId()) !== (int)$company->getId();
    }

    public function formatAmount(float $amount): string
    {
        return (string)$this->priceCurrency->format($amount, false);
    }

    /**
     * @param array<string, mixed> $entry
     */
    public function getEntryLabel(array $entry): string
    {
        $labels = [
            StoreCredit::TYPE_ADJUST => __('Adjustment'),
            StoreCredit::TYPE_ORDER => __('Order'),
            StoreCredit::TYPE_REFUND => __('Refund'),
            StoreCredit::TYPE_REVERT => __('Order cancelled'),
        ];

        return (string)($labels[$entry['type'] ?? ''] ?? __('Movement'));
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('b2b/company/index');
    }
}
