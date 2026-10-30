<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Adminhtml\Company;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Model\CompanyFactory;

class Edit extends AbstractCompany implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        CompanyRepositoryInterface $companyRepository,
        private readonly CompanyFactory $companyFactory
    ) {
        parent::__construct($context, $companyRepository);
    }

    public function execute(): ResultInterface
    {
        $companyId = (int)$this->getRequest()->getParam(CompanyInterface::ENTITY_ID);

        if ($companyId) {
            try {
                $company = $this->companyRepository->getById($companyId);
            } catch (NoSuchEntityException) {
                $this->messageManager->addErrorMessage(__('This company no longer exists.'));

                /** @var \Magento\Backend\Model\View\Result\Redirect $redirect */
                $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

                return $redirect->setPath('*/*/');
            }
        } else {
            $company = $this->companyFactory->create();
        }

        /** @var \Magento\Backend\Model\View\Result\Page $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $result->setActiveMenu(self::ADMIN_RESOURCE);
        $result->getConfig()->getTitle()->prepend(
            $companyId ? $company->getName() : __('New Company')
        );

        return $result;
    }
}
