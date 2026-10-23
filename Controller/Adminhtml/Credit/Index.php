<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Adminhtml\Credit;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * Store credit for one company: balance, adjustment form and history.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Tonyapps_B2b::companies';

    public function execute(): ResultInterface
    {
        if (!$this->getRequest()->getParam('company_id')) {
            $this->messageManager->addErrorMessage(__('Pick a company to manage credit for.'));

            /** @var \Magento\Backend\Model\View\Result\Redirect $redirect */
            $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

            return $redirect->setPath('tonyapps_b2b/company/index');
        }

        /** @var \Magento\Backend\Model\View\Result\Page $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $result->setActiveMenu(self::ADMIN_RESOURCE);
        $result->getConfig()->getTitle()->prepend(__('Store Credit'));

        return $result;
    }
}
