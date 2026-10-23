<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Block\Adminhtml\Role\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class SaveButton implements ButtonProviderInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        return [
            'label' => __('Save Role'),
            'class' => 'save primary',
            'sort_order' => 90,
            'data_attribute' => [
                'mage-init' => [
                    'buttonAdapter' => [
                        'actions' => [
                            [
                                'targetName' => 'tonyapps_b2b_role_form.tonyapps_b2b_role_form',
                                'actionName' => 'save',
                                'params' => [false],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
