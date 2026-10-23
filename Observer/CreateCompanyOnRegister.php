<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Observer;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;
use Tonyapps\B2b\Model\CompanyRegistration;
use Tonyapps\B2b\Model\Config;

/**
 * Creates the company for a customer who ticked the company box while registering.
 *
 * Runs after the account exists, because the company needs an administrator to point at. A
 * failure here is reported and logged but never rethrown: the customer's account has already been
 * created and they are already logged in, so throwing would strand them on an error page with an
 * account they do not know they have.
 */
class CreateCompanyOnRegister implements ObserverInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly Config $config,
        private readonly CompanyRegistration $companyRegistration,
        private readonly ManagerInterface $messageManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        if (!$this->config->isOfferedDuringCustomerRegistration()) {
            return;
        }

        $posted = $this->request->getPostValue('company');
        if (!is_array($posted) || empty($posted['register'])) {
            return;
        }

        $customer = $observer->getEvent()->getData('customer');
        if (!$customer || !$customer->getId()) {
            return;
        }

        try {
            $company = $this->companyRegistration->register((int)$customer->getId(), $posted);
            $this->messageManager->addSuccessMessage(
                __(
                    '%1 has been submitted and is waiting for approval. We will email you once it is reviewed.',
                    $company->getName()
                )
            );
        } catch (\Exception $exception) {
            $this->logger->error(
                'Tonyapps_B2b could not create a company during customer registration: ' . $exception->getMessage()
            );
            $this->messageManager->addErrorMessage(
                __('Your account was created, but we could not register your company. Please try again from My Company.')
            );
        }
    }
}
