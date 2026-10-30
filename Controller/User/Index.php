<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\User;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Controller\AbstractCompanyAction;
use Tonyapps\B2b\Model\Permissions;

/**
 * Company users list, for the company administrator.
 */
class Index extends AbstractCompanyAction implements HttpGetActionInterface
{
    protected string $requiredPermission = Permissions::USERS_VIEW;

    protected function executeForCompany(CompanyInterface $company): ResultInterface
    {
        return $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_PAGE);
    }
}
