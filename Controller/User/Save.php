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
use Psr\Log\LoggerInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Controller\AbstractCompanyAction;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2b\Model\Permissions;
use Tonyapps\B2b\Model\CompanyUserManagement;

/**
 * Adds a user to the company.
 */
class Save extends AbstractCompanyAction implements HttpPostActionInterface
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
        private readonly CompanyUserManagement $userManagement,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($request, $resultFactory, $companyContext, $customerSession, $messageManager, $url);
    }

    protected function executeForCompany(CompanyInterface $company): ResultInterface
    {
        if (!$this->formKeyValidator->validate($this->request)) {
            return $this->redirect('b2b/user/index');
        }

        $data = (array)$this->request->getPostValue('user', []);

        try {
            $this->userManagement->addUser(
                $company,
                (string)($data['email'] ?? ''),
                (string)($data['firstname'] ?? ''),
                (string)($data['lastname'] ?? ''),
                (int)($data['role_id'] ?? 0) ?: null,
                $this->companyContext->getCustomerId()
            );
            $this->messageManager->addSuccessMessage(
                __('%1 has been added to your company and emailed a link to set their password.', $data['email'] ?? '')
            );
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Exception $exception) {
            $this->logger->error('Tonyapps_B2b add company user failed: ' . $exception->getMessage());
            $this->messageManager->addErrorMessage(__('Something went wrong while adding that user.'));
        }

        return $this->redirect('b2b/user/index');
    }
}
