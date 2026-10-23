<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Controller\Adminhtml\Role;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Tonyapps\B2b\Api\RoleRepositoryInterface;
use Tonyapps\B2b\Model\RoleFactory;

abstract class AbstractRole extends Action
{
    public const ADMIN_RESOURCE = 'Tonyapps_B2b::companies';

    /**
     * Data persistor key for form values kept across a failed save.
     */
    public const DATA_PERSISTOR_KEY = 'tonyapps_b2b_role';

    public function __construct(
        Context $context,
        protected readonly RoleRepositoryInterface $roleRepository,
        protected readonly RoleFactory $roleFactory
    ) {
        parent::__construct($context);
    }
}
