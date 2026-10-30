<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Company;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Controller\AbstractCompanyAction;
use Tonyapps\B2b\Model\Permissions;

/**
 * Company profile form, for the company administrator.
 */
class Edit extends AbstractCompanyAction implements HttpGetActionInterface
{
    protected string $requiredPermission = Permissions::PROFILE_EDIT;

    protected function executeForCompany(CompanyInterface $company): ResultInterface
    {
        return $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_PAGE);
    }
}
