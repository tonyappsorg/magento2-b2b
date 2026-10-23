<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Block\Adminhtml\Company\Edit;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class BackButton implements ButtonProviderInterface
{
    public function __construct(
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        return [
            'label' => __('Back'),
            'class' => 'back',
            'sort_order' => 10,
            'on_click' => sprintf("location.href = '%s';", $this->urlBuilder->getUrl('tonyapps_b2b/company/index')),
        ];
    }
}
