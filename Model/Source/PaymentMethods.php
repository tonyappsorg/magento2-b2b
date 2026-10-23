<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Payment\Helper\Data as PaymentHelper;

/**
 * Every payment method the store has, active or not.
 *
 * Inactive ones are included on purpose: a company's allowed list should survive a method being
 * switched off and back on again.
 */
class PaymentMethods implements OptionSourceInterface
{
    public function __construct(
        private readonly PaymentHelper $paymentHelper
    ) {
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->paymentHelper->getPaymentMethods() as $code => $config) {
            $title = (string)($config['title'] ?? '');
            $options[] = [
                'value' => (string)$code,
                'label' => $title !== '' ? sprintf('%s (%s)', $title, $code) : (string)$code,
            ];
        }

        usort($options, static fn(array $a, array $b): int => strcasecmp($a['label'], $b['label']));

        return $options;
    }
}
