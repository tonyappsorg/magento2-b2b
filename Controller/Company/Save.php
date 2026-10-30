<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Company;

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
use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Controller\AbstractCompanyAction;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2b\Model\Permissions;

/**
 * Saves the company profile edited on the storefront.
 */
class Save extends AbstractCompanyAction implements HttpPostActionInterface
{
    protected string $requiredPermission = Permissions::PROFILE_EDIT;

    public function __construct(
        RequestInterface $request,
        ResultFactory $resultFactory,
        CompanyContext $companyContext,
        Session $customerSession,
        ManagerInterface $messageManager,
        UrlInterface $url,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($request, $resultFactory, $companyContext, $customerSession, $messageManager, $url);
    }

    protected function executeForCompany(CompanyInterface $company): ResultInterface
    {
        if (!$this->formKeyValidator->validate($this->request)) {
            return $this->redirect('b2b/company/edit');
        }

        $data = (array)$this->request->getPostValue('company', []);
        $name = trim((string)($data[CompanyInterface::NAME] ?? ''));
        if ($name === '') {
            $this->messageManager->addErrorMessage(__('Please enter a company name.'));

            return $this->redirect('b2b/company/edit');
        }

        $company->applyCustomerData($data);

        try {
            $this->companyRepository->save($company);
            $this->messageManager->addSuccessMessage(__('Your company profile has been saved.'));

            return $this->redirect('b2b/company/index');
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Exception $exception) {
            $this->logger->error('Tonyapps_B2b company profile save failed: ' . $exception->getMessage());
            $this->messageManager->addErrorMessage(__('Something went wrong while saving your company.'));
        }

        return $this->redirect('b2b/company/edit');
    }
}
