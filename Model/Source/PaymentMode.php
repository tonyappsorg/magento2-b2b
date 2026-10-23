<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;

class PaymentMode implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: int, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => CompanyInterface::PAYMENT_MODE_INHERIT,
                'label' => __('Inherit from parent company'),
            ],
            [
                'value' => CompanyInterface::PAYMENT_MODE_ALL,
                'label' => __('Allow every payment method'),
            ],
            [
                'value' => CompanyInterface::PAYMENT_MODE_RESTRICT,
                'label' => __('Allow only the selected methods'),
            ],
        ];
    }
}
