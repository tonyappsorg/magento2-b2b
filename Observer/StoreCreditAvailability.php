<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Observer;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Tonyapps\B2b\Model\ResourceModel\Company as CompanyResource;
use Tonyapps\B2b\Api\MembershipManagementInterface;
use Tonyapps\B2b\Model\Config;
use Tonyapps\B2b\Model\Payment\StoreCreditFacade;
use Tonyapps\B2b\Model\PermissionChecker;
use Tonyapps\B2b\Model\Permissions;
use Tonyapps\B2b\Model\StoreCredit;

/**
 * Offers store credit only when it can pay for the whole order.
 *
 * The agreed scope is full coverage or nothing: there is no total collector and no split payment,
 * so a balance that only covers part of the basket is no use and the method stays hidden rather
 * than failing at the end of checkout.
 */
class StoreCreditAvailability implements ObserverInterface
{
    public function __construct(
        private readonly StoreCredit $storeCredit,
        private readonly MembershipManagementInterface $membership,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly CustomerSession $customerSession,
        private readonly CompanyResource $companyResource,
        private readonly Config $config,
        private readonly PermissionChecker $permissionChecker,
        private readonly State $appState
    ) {
    }

    public function execute(Observer $observer): void
    {
        $result = $observer->getEvent()->getData('result');
        $methodInstance = $observer->getEvent()->getData('method_instance');

        if (!$result
            || !$methodInstance
            || (string)$methodInstance->getCode() !== StoreCreditFacade::CODE
            || $result->getData('is_available') === false
        ) {
            return;
        }

        $result->setData('is_available', $this->isAvailable($observer));
    }

    private function isAvailable(Observer $observer): bool
    {
        $quote = $observer->getEvent()->getData('quote');
        $customerId = $quote && $quote->getCustomerId()
            ? (int)$quote->getCustomerId()
            : (int)$this->customerSession->getCustomerId();

        if (!$customerId || !$this->config->isEnabled()) {
            return false;
        }

        $companyId = $this->membership->getCompanyId($customerId);
        if ($companyId === null) {
            return false;
        }

        try {
            $company = $this->companyRepository->getById($companyId);
        } catch (\Exception) {
            return false;
        }

        // The pool belongs to the root; a blocked company anywhere above this one switches it off.
        if (!$this->companyResource->isActivePath((int)$company->getId())) {
            return false;
        }

        // Spending the shared pool is a company permission; an administrator creating the order in the admin
        // has already decided. Core re-checks availability when the order is placed, so this also guards the API.
        if ($this->appState->getAreaCode() !== Area::AREA_ADMINHTML
            && !$this->permissionChecker->isAllowed($customerId, $company, Permissions::CREDIT_USE)
        ) {
            return false;
        }

        // With no quote there is no total to compare against; showing the method is better than
        // hiding it from a listing that is not about a specific basket.
        if (!$quote) {
            return $this->storeCredit->getBalance($companyId) > 0;
        }

        return $this->storeCredit->canCover($companyId, (float)$quote->getBaseGrandTotal());
    }
}
