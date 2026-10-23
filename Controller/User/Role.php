<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\User;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Controller\AbstractCompanyAction;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2b\Model\CompanyUserManagement;
use Tonyapps\B2b\Model\Permissions;

/**
 * Changes which role one of the company's users holds.
 */
class Role extends AbstractCompanyAction implements HttpPostActionInterface
{
    protected string $requiredPermission = Permissions::USERS_MANAGE;

    public function __construct(
        RequestInterface $request,
        ResultFactory $resultFactory,
        CompanyContext $companyContext,
        Session $customerSession,
        ManagerInterface $messageManager,
        UrlInterface $url,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly CompanyUserManagement $userManagement
    ) {
        parent::__construct($request, $resultFactory, $companyContext, $customerSession, $messageManager, $url);
    }

    protected function executeForCompany(CompanyInterface $company): ResultInterface
    {
        if (!$this->formKeyValidator->validate($this->request)) {
            return $this->redirect('b2b/user/index');
        }

        try {
            $this->userManagement->setUserRole(
                $company,
                (int)$this->request->getParam('customer_id'),
                (int)$this->request->getParam('role_id') ?: null
            );
            $this->messageManager->addSuccessMessage(__('The role has been changed.'));
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $this->redirect('b2b/user/index');
    }
}
