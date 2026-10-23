<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model\Source;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\OptionSourceInterface;
use Tonyapps\B2b\Model\ResourceModel\Company as CompanyResource;

/**
 * Companies as select options.
 *
 * On the company form the request carries the company being edited; its own subtree is excluded
 * so a cycle cannot be selected as a parent in the first place.
 */
class CompanyList implements OptionSourceInterface
{
    public function __construct(
        private readonly CompanyResource $companyResource,
        private readonly RequestInterface $request,
        private readonly bool $excludeCurrentSubtree = false,
        private readonly string $emptyLabel = 'No Parent Company'
    ) {
    }

    /**
     * @return array<int, array{value: string, label: string|\Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        $exclude = null;
        if ($this->excludeCurrentSubtree && $companyId = (int)$this->request->getParam('entity_id')) {
            $exclude = $companyId;
        }

        $options = [['value' => '', 'label' => __($this->emptyLabel)]];
        foreach ($this->companyResource->getCompanyOptions($exclude) as $value => $label) {
            $options[] = ['value' => (string)$value, 'label' => $label];
        }

        return $options;
    }
}
