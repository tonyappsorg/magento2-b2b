<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Adminhtml\Company;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Registry;
use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Tonyapps\B2b\Model\CompanyFactory;

abstract class AbstractCompany extends Action
{
    public const ADMIN_RESOURCE = 'Tonyapps_B2b::companies';

    /**
     * Registry key holding the company currently being edited.
     */
    public const REGISTRY_KEY = 'tonyapps_b2b_current_company';

    /**
     * Data persistor key for form values kept across a failed save.
     */
    public const DATA_PERSISTOR_KEY = 'tonyapps_b2b_company';

    public function __construct(
        Context $context,
        protected readonly CompanyRepositoryInterface $companyRepository,
        protected readonly CompanyFactory $companyFactory,
        protected readonly Registry $coreRegistry
    ) {
        parent::__construct($context);
    }
}
