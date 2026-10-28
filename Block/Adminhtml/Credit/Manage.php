<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Block\Adminhtml\Credit;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Exception\NoSuchEntityException;
use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Model\StoreCredit;

/**
 * Admin store credit screen for one company.
 */
class Manage extends Template
{
    public function __construct(
        Context $context,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly StoreCredit $storeCredit,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getCompanyId(): int
    {
        return (int)$this->getRequest()->getParam('company_id');
    }

    public function getCompany(): ?CompanyInterface
    {
        try {
            return $this->companyRepository->getById($this->getCompanyId());
        } catch (NoSuchEntityException) {
            return null;
        }
    }

    public function getBalance(): float
    {
        return $this->storeCredit->getBalance($this->getCompanyId());
    }

    /**
     * The company that actually owns the pool, when this one spends from an ancestor's.
     */
    public function getPoolCompany(): ?CompanyInterface
    {
        $poolId = $this->storeCredit->getPoolCompanyId($this->getCompanyId());
        if ($poolId === $this->getCompanyId()) {
            return null;
        }

        try {
            return $this->companyRepository->getById($poolId);
        } catch (NoSuchEntityException) {
            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getHistory(): array
    {
        return $this->storeCredit->getHistory($this->getCompanyId(), 100);
    }

    /**
     * @param array<string, mixed> $entry
     */
    public function getEntryLabel(array $entry): string
    {
        $labels = [
            StoreCredit::TYPE_ADJUST => __('Manual adjustment'),
            StoreCredit::TYPE_ORDER => __('Spent on an order'),
            StoreCredit::TYPE_REFUND => __('Refunded'),
            StoreCredit::TYPE_REVERT => __('Order cancelled'),
        ];

        return (string)($labels[$entry['type'] ?? ''] ?? __('Movement'));
    }

    public function canAdjust(): bool
    {
        return $this->_authorization->isAllowed('Tonyapps_B2b::credit');
    }

    public function getAdjustUrl(): string
    {
        return $this->getUrl('tonyapps_b2b/credit/adjust', ['company_id' => $this->getCompanyId()]);
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('tonyapps_b2b/company/index');
    }

    public function getCompanyEditUrl(): string
    {
        return $this->getUrl('tonyapps_b2b/company/edit', ['entity_id' => $this->getCompanyId()]);
    }
}
