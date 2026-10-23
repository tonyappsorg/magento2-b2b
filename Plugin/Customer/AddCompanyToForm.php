<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Plugin\Customer;

use Magento\Customer\Model\Customer\DataProviderWithDefaultAddresses;
use Tonyapps\B2b\Api\MembershipManagementInterface;

/**
 * Feeds the current company into the Company fieldset of the admin customer form.
 *
 * The customer form data provider has no modifier pool, so the value is appended to its result.
 */
class AddCompanyToForm
{
    /**
     * Data scope of the fieldset declared in view/adminhtml/ui_component/customer_form.xml.
     */
    public const DATA_SCOPE = 'tonyapps_b2b';

    public function __construct(
        private readonly MembershipManagementInterface $membership
    ) {
    }

    /**
     * @param DataProviderWithDefaultAddresses $subject
     * @param array<int|string, array<string, mixed>> $result
     * @return array<int|string, array<string, mixed>>
     */
    public function afterGetData(DataProviderWithDefaultAddresses $subject, array $result): array
    {
        $customerIds = array_values(array_filter(array_map('intval', array_keys($result))));
        if (!$customerIds) {
            return $result;
        }

        $companyIds = $this->membership->getCompanyIds($customerIds);
        foreach ($result as $customerId => &$data) {
            $data[self::DATA_SCOPE]['company_id'] = $companyIds[(int)$customerId] ?? '';
        }

        return $result;
    }
}
