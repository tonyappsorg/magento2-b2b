<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model;

use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;

/**
 * Applies the side effects of a company's status changing.
 *
 * Everything that approves, rejects or blocks a company goes through here — the admin form, the
 * grid mass action — so the customer groups and the notification email can never be forgotten by
 * one caller and remembered by another.
 */
class CompanyStatus
{
    public function __construct(
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly CustomerGroupSync $customerGroupSync,
        private readonly CompanyEmail $companyEmail
    ) {
    }

    /**
     * Move a company to a new status, with its side effects.
     *
     * @return bool whether anything changed
     */
    public function change(CompanyInterface $company, int $newStatus): bool
    {
        $oldStatus = $company->getStatus();
        if ($oldStatus === $newStatus) {
            return false;
        }

        $company->setStatus($newStatus);
        $this->companyRepository->save($company);
        $this->applySideEffects($company, $oldStatus, $newStatus);

        return true;
    }

    /**
     * Side effects of a status the caller has already saved.
     *
     * Split out for the admin form, which saves the whole company in one go and only afterwards
     * knows the status moved.
     */
    public function applySideEffects(CompanyInterface $company, int $oldStatus, int $newStatus): void
    {
        // Groups are re-applied on every save, not only on a status change: the customer group
        // and the parent company can both move what a subtree resolves to. It is idempotent.
        $companyId = (int)$company->getId();

        if ($newStatus === CompanyInterface::STATUS_ACTIVE) {
            $this->customerGroupSync->syncCompanyTree($companyId);
        } else {
            // Pending, rejected and blocked companies do not confer their group on anyone.
            $this->customerGroupSync->releaseCompanyTree($companyId);
        }

        // Only tell the customer about the decision on their pending request; going from active
        // to blocked and back is administrative noise they did not ask about, and a save that did
        // not move the status is not a decision at all.
        if ($oldStatus === $newStatus || $oldStatus !== CompanyInterface::STATUS_PENDING) {
            return;
        }

        if ($newStatus === CompanyInterface::STATUS_ACTIVE) {
            $this->companyEmail->sendApproved($company);
        } elseif ($newStatus === CompanyInterface::STATUS_REJECTED) {
            $this->companyEmail->sendRejected($company);
        }
    }
}
