<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Observer;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Tonyapps\B2b\Api\MembershipManagementInterface;
use Tonyapps\B2b\Plugin\Customer\AddCompanyToForm;

/**
 * Applies the Company fieldset of the admin customer form.
 *
 * Company membership is not a customer attribute, so it is not carried by the customer object the
 * save controller builds; the raw request is the only place it appears.
 *
 * Saving a customer only needs Magento_Customer::manage; moving them into a company needs the
 * same Tonyapps_B2b::companies as every other company screen.
 */
class SaveCustomerCompany implements ObserverInterface
{
    public function __construct(
        private readonly MembershipManagementInterface $membership,
        private readonly AuthorizationInterface $authorization
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var \Magento\Framework\App\RequestInterface $request */
        $request = $observer->getEvent()->getData('request');
        $customer = $observer->getEvent()->getData('customer');

        if (!$request || !$customer || !$customer->getId()
            || !$this->authorization->isAllowed('Tonyapps_B2b::companies')
        ) {
            return;
        }

        $posted = $request->getPostValue(AddCompanyToForm::DATA_SCOPE);
        // A save that never rendered the fieldset (an API call, another form) must not clear the
        // customer's company, so only act when the field was actually submitted.
        if (!is_array($posted) || !array_key_exists('company_id', $posted)) {
            return;
        }

        $companyId = (int)$posted['company_id'] ?: null;
        $this->membership->assign((int)$customer->getId(), $companyId);
    }
}
