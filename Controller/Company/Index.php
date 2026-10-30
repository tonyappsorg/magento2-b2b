<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Company;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Controller\AbstractCompanyAction;

/**
 * Company dashboard: profile summary, visible to every member.
 */
class Index extends AbstractCompanyAction implements HttpGetActionInterface
{
    protected function executeForCompany(CompanyInterface $company): ResultInterface
    {
        return $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_PAGE);
    }
}
