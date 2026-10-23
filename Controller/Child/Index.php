<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Child;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Controller\AbstractCompanyAction;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2b\Model\Permissions;

/**
 * Child companies: the tree below this company, and the form for adding to it.
 */
class Index extends AbstractCompanyAction implements HttpGetActionInterface
{
    protected string $requiredPermission = Permissions::CHILDREN_VIEW;

    public function __construct(
        RequestInterface $request,
        ResultFactory $resultFactory,
        CompanyContext $companyContext,
        Session $customerSession,
        ManagerInterface $messageManager,
        UrlInterface $url,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($request, $resultFactory, $companyContext, $customerSession, $messageManager, $url);
    }

    protected function executeForCompany(CompanyInterface $company): ResultInterface
    {
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Child Companies'));

        return $page;
    }
}
