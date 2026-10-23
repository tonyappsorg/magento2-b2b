<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Company;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2b\Model\CompanyRegistration;

/**
 * Handles the storefront company registration submission.
 */
class CreatePost implements HttpPostActionInterface
{
    public const DATA_PERSISTOR_KEY = 'tonyapps_b2b_company_register';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResultFactory $resultFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly CompanyContext $companyContext,
        private readonly CompanyRegistration $companyRegistration,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly ManagerInterface $messageManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->formKeyValidator->validate($this->request)) {
            return $this->redirect('b2b/company/create');
        }

        $customerId = $this->companyContext->getCustomerId();
        if (!$this->companyContext->isEnabled() || $customerId === null) {
            return $this->redirect('customer/account/login');
        }

        if (!$this->companyContext->canRegisterCompany()) {
            $this->messageManager->addErrorMessage(
                __('You already belong to a company. Ask your company administrator to add what you need.')
            );

            return $this->redirect('b2b/company/index');
        }

        $data = (array)$this->request->getPostValue('company', []);

        try {
            $company = $this->companyRegistration->register($customerId, $data);
            $this->dataPersistor->clear(self::DATA_PERSISTOR_KEY);
            $this->messageManager->addSuccessMessage(
                __(
                    'Thank you. %1 has been submitted and is waiting for approval. We will email you once it is reviewed.',
                    $company->getName()
                )
            );

            return $this->redirect('b2b/company/index');
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Exception $exception) {
            $this->logger->error('Tonyapps_B2b company registration failed: ' . $exception->getMessage());
            $this->messageManager->addErrorMessage(
                __('Something went wrong while registering your company. Please try again.')
            );
        }

        $this->dataPersistor->set(self::DATA_PERSISTOR_KEY, $data);

        return $this->redirect('b2b/company/create');
    }

    private function redirect(string $path): ResultInterface
    {
        /** @var \Magento\Framework\Controller\Result\Redirect $redirect */
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $redirect->setPath($path);
    }
}
