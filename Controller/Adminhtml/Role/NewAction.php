<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Adminhtml\Role;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\ResultFactory;

class NewAction extends AbstractRole implements HttpGetActionInterface
{
    public function execute(): Forward
    {
        /** @var Forward $forward */
        $forward = $this->resultFactory->create(ResultFactory::TYPE_FORWARD);

        return $forward->forward('edit');
    }
}
