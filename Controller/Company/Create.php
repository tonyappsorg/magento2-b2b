<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Company;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Tonyapps\B2b\Model\CompanyContext;

/**
 * Storefront company registration form.
 */
class Create implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly ResultFactory $resultFactory,
        private readonly CompanyContext $companyContext,
        private readonly Session $customerSession,
        private readonly ManagerInterface $messageManager,
        private readonly UrlInterface $url
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->companyContext->isEnabled() || !$this->companyContext->getCustomerId()) {
            // Registering a company needs an account: the submitter becomes its administrator.
            $this->customerSession->setAfterAuthUrl($this->url->getUrl('b2b/company/create'));

            return $this->redirect('customer/account/login');
        }

        if (!$this->companyContext->canRegisterCompany()) {
            $this->messageManager->addErrorMessage(
                __('You already belong to a company. Ask your company administrator to add what you need.')
            );

            return $this->redirect('b2b/company/index');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Register a Company'));

        return $page;
    }

    private function redirect(string $path): ResultInterface
    {
        /** @var \Magento\Framework\Controller\Result\Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $redirect->setPath($path);
    }
}
