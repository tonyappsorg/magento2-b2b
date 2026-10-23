<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Adminhtml\Customer;

use Magento\Backend\App\Action\Context;
use Magento\Customer\Controller\Adminhtml\Index\AbstractMassAction;
use Magento\Customer\Model\ResourceModel\Customer\CollectionFactory;
use Magento\Eav\Model\Entity\Collection\AbstractCollection;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Ui\Component\MassAction\Filter;
use Tonyapps\B2b\Api\MembershipManagementInterface;

/**
 * Customer grid mass action: move the selected customers into a company, or out of one.
 *
 * Reuses Magento's customer mass-action base for the grid filter plumbing, but is gated by the
 * company ACL resource rather than Magento_Customer::manage.
 */
class MassAssignCompany extends AbstractMassAction implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Tonyapps_B2b::companies';

    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory,
        private readonly MembershipManagementInterface $membership
    ) {
        parent::__construct($context, $filter, $collectionFactory);
    }

    /**
     * @param AbstractCollection $collection
     * @return Redirect
     */
    protected function massAction(AbstractCollection $collection): Redirect
    {
        // 0 is the "Remove from Company" option; there is no company with that ID.
        $companyId = (int)$this->getRequest()->getParam('company_id') ?: null;
        $updated = 0;

        foreach ($collection->getAllIds() as $customerId) {
            $this->membership->assign((int)$customerId, $companyId);
            $updated++;
        }

        $this->messageManager->addSuccessMessage(
            $companyId === null
                ? __('A total of %1 customer(s) were removed from their company.', $updated)
                : __('A total of %1 customer(s) were assigned to the company.', $updated)
        );

        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        return $resultRedirect->setPath($this->getComponentRefererUrl());
    }
}
