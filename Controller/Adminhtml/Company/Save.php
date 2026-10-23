<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Adminhtml\Company;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Model\CompanyFactory;
use Tonyapps\B2b\Model\CompanyStatus;
use Tonyapps\B2b\Model\ResourceModel\CompanyPayment;

class Save extends AbstractCompany implements HttpPostActionInterface
{
    /**
     * Optional foreign keys and the group: an empty select must store NULL, not 0 or "".
     */
    private const NULLABLE_FIELDS = [
        CompanyInterface::PARENT_ID,
        CompanyInterface::CUSTOMER_GROUP_ID,
        CompanyInterface::ADMIN_CUSTOMER_ID,
        CompanyInterface::REGION_ID,
    ];

    public function __construct(
        Context $context,
        CompanyRepositoryInterface $companyRepository,
        CompanyFactory $companyFactory,
        Registry $coreRegistry,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly CompanyStatus $companyStatus,
        private readonly CompanyPayment $companyPayment
    ) {
        parent::__construct($context, $companyRepository, $companyFactory, $coreRegistry);
    }

    public function execute(): Redirect
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultRedirectFactory->create();

        $data = $this->getRequest()->getPostValue();
        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        $companyId = (int)($data[CompanyInterface::ENTITY_ID] ?? 0);

        try {
            $company = $companyId
                ? $this->companyRepository->getById($companyId)
                : $this->companyFactory->create();
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('This company no longer exists.'));

            return $resultRedirect->setPath('*/*/');
        }

        // -1 for a company that does not exist yet: no status it could have moved away from, so
        // the side effects run but the customer is not emailed about a decision they never asked for.
        $oldStatus = $companyId ? $company->getStatus() : -1;
        // Not a company column; pulled out before the rest is handed to the model.
        $paymentMethods = (array)($data['payment_methods'] ?? []);
        $company->addData($this->prepareData($data));

        try {
            $this->companyRepository->save($company);
            $this->companyPayment->setMethodCodes((int)$company->getId(), $paymentMethods);
            // Both the group and the parent can change what members resolve to, so re-apply
            // across the whole subtree rather than working out whether it moved.
            $this->companyStatus->applySideEffects($company, $oldStatus, $company->getStatus());

            $this->messageManager->addSuccessMessage(__('The company has been saved.'));
            $this->dataPersistor->clear(self::DATA_PERSISTOR_KEY);

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath(
                    '*/*/edit',
                    [CompanyInterface::ENTITY_ID => $company->getId()]
                );
            }

            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Exception $exception) {
            $this->messageManager->addExceptionMessage($exception, __('Could not save the company.'));
        }

        $this->dataPersistor->set(self::DATA_PERSISTOR_KEY, $data);

        return $companyId
            ? $resultRedirect->setPath('*/*/edit', [CompanyInterface::ENTITY_ID => $companyId])
            : $resultRedirect->setPath('*/*/new');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function prepareData(array $data): array
    {
        unset(
            $data['form_key'],
            $data['back'],
            $data['payment_methods'],
            $data[CompanyInterface::PATH],
            $data[CompanyInterface::LEVEL]
        );

        if (!($data[CompanyInterface::ENTITY_ID] ?? null)) {
            unset($data[CompanyInterface::ENTITY_ID]);
        }

        foreach (self::NULLABLE_FIELDS as $field) {
            if (isset($data[$field]) && ($data[$field] === '' || $data[$field] === '0')) {
                $data[$field] = null;
            }
        }

        return $data;
    }
}
