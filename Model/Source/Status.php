<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;

class Status implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: int, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => CompanyInterface::STATUS_PENDING, 'label' => __('Pending Approval')],
            ['value' => CompanyInterface::STATUS_ACTIVE, 'label' => __('Active')],
            ['value' => CompanyInterface::STATUS_REJECTED, 'label' => __('Rejected')],
            ['value' => CompanyInterface::STATUS_BLOCKED, 'label' => __('Blocked')],
        ];
    }
}
