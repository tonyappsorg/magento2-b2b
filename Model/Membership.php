<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Tonyapps\B2b\Api\MembershipManagementInterface;
use Tonyapps\B2b\Model\ResourceModel\CompanyCustomer;

/**
 * Moves customers in and out of companies, keeping their customer group in step.
 */
class Membership implements MembershipManagementInterface
{
    public function __construct(
        private readonly CompanyCustomer $companyCustomer,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly CustomerGroupSync $customerGroupSync
    ) {
    }

    /**
     * @throws NoSuchEntityException when the target company does not exist
     */
    public function assign(int $customerId, ?int $companyId, ?int $roleId = null): void
    {
        $previousCompanyId = $this->getCompanyId($customerId);
        if ($previousCompanyId === $companyId) {
            // Already in this company; the caller may still be changing their role.
            if ($companyId !== null && $roleId !== null) {
                $this->setRole($customerId, $roleId);
            }

            return;
        }

        if ($companyId === null) {
            $this->companyCustomer->unassign($customerId);
        } else {
            // Fails loudly rather than writing a membership row pointing at nothing.
            $this->companyRepository->getById($companyId);
            $this->companyCustomer->assign($customerId, $companyId, $roleId);
        }

        $this->customerGroupSync->syncCustomer($customerId, $companyId, $previousCompanyId);
    }

    public function setRole(int $customerId, ?int $roleId): void
    {
        $this->companyCustomer->setRoleId($customerId, $roleId);
    }

    public function getCompanyId(int $customerId): ?int
    {
        return $this->companyCustomer->getCompanyId($customerId);
    }

    public function getCompanyIds(array $customerIds): array
    {
        return $this->companyCustomer->getCompanyIds($customerIds);
    }
}
