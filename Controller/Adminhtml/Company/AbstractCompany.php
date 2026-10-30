<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Adminhtml\Company;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Tonyapps\B2b\Api\CompanyRepositoryInterface;

abstract class AbstractCompany extends Action
{
    public const ADMIN_RESOURCE = 'Tonyapps_B2b::companies';

    /**
     * Data persistor key for form values kept across a failed save.
     */
    public const DATA_PERSISTOR_KEY = 'tonyapps_b2b_company';

    public function __construct(
        Context $context,
        protected readonly CompanyRepositoryInterface $companyRepository
    ) {
        parent::__construct($context);
    }
}
