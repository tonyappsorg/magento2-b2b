<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Order;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Controller\AbstractCompanyAction;
use Tonyapps\B2b\Model\Permissions;

/**
 * Orders placed across the company and everything below it.
 *
 * A separate page from My Orders rather than extra rows inside it: a buyer looking for their own
 * order should not have to scroll past everyone else's.
 */
class Index extends AbstractCompanyAction implements HttpGetActionInterface
{
    protected string $requiredPermission = Permissions::ORDERS_VIEW_COMPANY;

    protected function executeForCompany(CompanyInterface $company): ResultInterface
    {
        return $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_PAGE);
    }
}
