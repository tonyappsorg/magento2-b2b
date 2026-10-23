<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Child;

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
use Tonyapps\B2b\Model\ChildCompanyManagement;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2b\Model\Permissions;

/**
 * Adds a child company, or saves changes to one.
 */
class Save extends AbstractCompanyAction implements HttpPostActionInterface
{
    protected string $requiredPermission = Permissions::CHILDREN_MANAGE;

    public function __construct(
        RequestInterface $request,
        ResultFactory $resultFactory,
        CompanyContext $companyContext,
        Session $customerSession,
        ManagerInterface $messageManager,
        UrlInterface $url,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly ChildCompanyManagement $childCompanies,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($request, $resultFactory, $companyContext, $customerSession, $messageManager, $url);
    }

    protected function executeForCompany(CompanyInterface $company): ResultInterface
    {
        if (!$this->formKeyValidator->validate($this->request)) {
            return $this->redirect('b2b/child/index');
        }

        $data = (array)$this->request->getPostValue('company', []);
        $childId = (int)($data[CompanyInterface::ENTITY_ID] ?? 0);

        try {
            if ($childId) {
                $child = $this->childCompanies->update($company, $childId, $data);
                $this->messageManager->addSuccessMessage(__('%1 has been saved.', $child->getName()));
            } else {
                $child = $this->childCompanies->create($company, $data);
                $this->messageManager->addSuccessMessage(__('%1 has been added.', $child->getName()));
            }
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Exception $exception) {
            $this->logger->error('Tonyapps_B2b child company save failed: ' . $exception->getMessage());
            $this->messageManager->addErrorMessage(__('Something went wrong while saving that company.'));
        }

        return $this->redirect('b2b/child/index');
    }
}
