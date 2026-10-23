<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model;

use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Api\MembershipManagementInterface;
use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Tonyapps\B2b\Model\ResourceModel\Company as CompanyResource;

/**
 * Whether a given customer may pay with a given method.
 *
 * Kept apart from the observer so the same answer can be asked for anywhere — an order placed in
 * the admin, an API call, a later phase's checkout — without going through an event.
 */
class PaymentRestriction
{
    /**
     * @var array<int, string[]|null>
     */
    private array $cache = [];

    public function __construct(
        private readonly MembershipManagementInterface $membership,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly CompanyResource $companyResource,
        private readonly SettingResolver $settingResolver,
        private readonly Config $config
    ) {
    }

    public function isMethodAllowed(?int $customerId, string $methodCode): bool
    {
        $allowed = $this->getAllowedMethods($customerId);

        return $allowed === null || in_array($methodCode, $allowed, true);
    }

    /**
     * The methods this customer may use, or NULL when nothing restricts them.
     *
     * @return string[]|null
     */
    public function getAllowedMethods(?int $customerId): ?array
    {
        if ($customerId === null || !$this->config->isEnabled()) {
            return null;
        }

        if (array_key_exists($customerId, $this->cache)) {
            return $this->cache[$customerId];
        }

        return $this->cache[$customerId] = $this->resolve($customerId);
    }

    /**
     * @return string[]|null
     */
    private function resolve(int $customerId): ?array
    {
        $companyId = $this->membership->getCompanyId($customerId);
        if ($companyId === null) {
            return null;
        }

        try {
            $company = $this->companyRepository->getById($companyId);
        } catch (NoSuchEntityException) {
            return null;
        }

        // A company that is not active has no say over payment; its people are ordinary customers
        // until an administrator approves or unblocks it.
        if ($company->getStatus() !== CompanyInterface::STATUS_ACTIVE) {
            return null;
        }

        return $this->settingResolver->resolvePaymentMethods($this->companyResource->getPathById($companyId));
    }

    public function reset(): void
    {
        $this->cache = [];
    }
}
