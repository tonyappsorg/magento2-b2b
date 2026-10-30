<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller;

use Magento\Customer\Model\Session;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2b\Model\Permissions;

/**
 * Shared guard for the storefront company pages.
 *
 * Every company page needs the same three answers — is the feature on, is anybody logged in, do
 * they have a company — and the ones that change things additionally need the visitor to be the
 * company administrator. Putting it here means a new page cannot forget the check.
 */
abstract class AbstractCompanyAction implements ActionInterface
{
    /**
     * Permission this page needs, from Tonyapps\B2b\Model\Permissions.
     *
     * Declared per controller rather than mapped in an observer: a new page cannot be added
     * without deciding what it requires, and the requirement sits where the page is. An empty
     * string means membership alone is enough, whatever the company's status.
     */
    protected string $requiredPermission = Permissions::PROFILE_VIEW;

    public function __construct(
        protected readonly RequestInterface $request,
        protected readonly ResultFactory $resultFactory,
        protected readonly CompanyContext $companyContext,
        protected readonly Session $customerSession,
        protected readonly ManagerInterface $messageManager,
        protected readonly UrlInterface $url
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->companyContext->isEnabled()) {
            return $this->redirect('customer/account');
        }

        if ($this->companyContext->getCustomerId() === null) {
            $this->customerSession->setAfterAuthUrl($this->url->getUrl('*/*/*'));

            return $this->redirect('customer/account/login');
        }

        $company = $this->companyContext->getCompany();
        if ($company === null) {
            return $this->redirect('customer/account');
        }

        if ($this->requiredPermission !== '' && !$this->companyContext->isAllowed($this->requiredPermission)) {
            $this->messageManager->addErrorMessage(
                __('You do not have permission to do that. Ask your company administrator.')
            );

            // Somebody who cannot even see the company page has nowhere to be sent inside it.
            return $this->redirect(
                $this->requiredPermission === Permissions::PROFILE_VIEW ? 'customer/account' : 'b2b/company/index'
            );
        }

        return $this->executeForCompany($company);
    }

    abstract protected function executeForCompany(CompanyInterface $company): ResultInterface;

    protected function redirect(string $path): Redirect
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $redirect->setPath($path);
    }

}
