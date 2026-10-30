<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Company;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Controller\AbstractCompanyAction;

/**
 * Company dashboard: profile summary, visible to every member.
 *
 * Membership is enough, not a permission: a company awaiting approval (or blocked) grants none,
 * and this page is where its members learn that.
 */
class Index extends AbstractCompanyAction implements HttpGetActionInterface
{
    protected string $requiredPermission = '';

    protected function executeForCompany(CompanyInterface $company): ResultInterface
    {
        return $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_PAGE);
    }
}
