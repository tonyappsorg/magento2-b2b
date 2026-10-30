<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model;

use Magento\Customer\Model\Session;
use Magento\Framework\Exception\NoSuchEntityException;
use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Api\MembershipManagementInterface;

/**
 * Who the current storefront visitor is, company-wise.
 *
 * Resolves the current customer's company and checks company feature access.
 */
class CompanyContext
{
    private ?CompanyInterface $company = null;
    private bool $loaded = false;

    public function __construct(
        private readonly Session $customerSession,
        private readonly MembershipManagementInterface $membership,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly Config $config,
        private readonly PermissionChecker $permissionChecker
    ) {
    }

    public function getCustomerId(): ?int
    {
        $customerId = (int)$this->customerSession->getCustomerId();

        return $customerId ?: null;
    }

    /**
     * The visitor's company, or NULL when they have none (or are not logged in).
     */
    public function getCompany(): ?CompanyInterface
    {
        if ($this->loaded) {
            return $this->company;
        }

        $this->loaded = true;
        $customerId = $this->getCustomerId();
        $companyId = $customerId === null ? null : $this->membership->getCompanyId($customerId);

        if ($companyId !== null) {
            try {
                $this->company = $this->companyRepository->getById($companyId);
            } catch (NoSuchEntityException) {
                $this->company = null;
            }
        }

        return $this->company;
    }

    /**
     * Whether the visitor holds a permission in their company.
     *
     * @see Permissions for the catalogue of permission names.
     */
    public function isAllowed(string $permission): bool
    {
        // The master switch turns off everything the storefront gates on a permission.
        return $this->isEnabled()
            && $this->permissionChecker->isAllowed($this->getCustomerId(), $this->getCompany(), $permission);
    }

    /**
     * Whether company features should be visible at all on this store.
     */
    public function isEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    /**
     * Whether the visitor may submit a company registration.
     *
     * Someone who already belongs to a company cannot start another: a customer belongs to at
     * most one, so the answer is to ask their company administrator.
     */
    public function canRegisterCompany(): bool
    {
        return $this->config->isRegistrationEnabled() && $this->getCompany() === null;
    }
}
