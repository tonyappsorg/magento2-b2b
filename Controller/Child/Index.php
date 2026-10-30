<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Child;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Controller\AbstractCompanyAction;
use Tonyapps\B2b\Model\Permissions;

/**
 * Child companies: the tree below this company, and the form for adding to it.
 */
class Index extends AbstractCompanyAction implements HttpGetActionInterface
{
    protected string $requiredPermission = Permissions::CHILDREN_VIEW;

    protected function executeForCompany(CompanyInterface $company): ResultInterface
    {
        return $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_PAGE);
    }
}
