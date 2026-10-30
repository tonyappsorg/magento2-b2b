<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model;

use Magento\Framework\Exception\LocalizedException;
use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Api\MembershipManagementInterface;

/**
 * Creates a company from storefront-submitted data.
 *
 * Shared by the standalone registration page and the optional section on the Create an Account
 * form, so both produce exactly the same company.
 */
class CompanyRegistration
{
    public function __construct(
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly CompanyFactory $companyFactory,
        private readonly MembershipManagementInterface $membership,
        private readonly CompanyEmail $companyEmail
    ) {
    }

    /**
     * Register a company for a customer and make them its administrator.
     *
     * The company always starts pending: it confers no customer group until an administrator
     * approves it.
     *
     * @param array<string, mixed> $data raw form input
     * @throws LocalizedException
     */
    public function register(int $customerId, array $data): CompanyInterface
    {
        if ($this->membership->getCompanyId($customerId) !== null) {
            throw new LocalizedException(
                __('You already belong to a company. Ask your company administrator to add what you need.')
            );
        }

        $name = trim((string)($data[CompanyInterface::NAME] ?? ''));
        if ($name === '') {
            throw new LocalizedException(__('Please enter a company name.'));
        }

        $company = $this->companyFactory->create();
        $company->applyCustomerData($data);
        $company->setStatus(CompanyInterface::STATUS_PENDING);
        $company->setAdminCustomerId($customerId);
        $company->setCustomerGroupId(null);
        $company->setParentId(null);

        $this->companyRepository->save($company);

        // Membership applies no group of its own while the company is pending; this is only so
        // the customer can see their company in their account straight away.
        $this->membership->assign($customerId, (int)$company->getId());

        $this->companyEmail->sendNewCompanyNotification($company);

        return $company;
    }
}
