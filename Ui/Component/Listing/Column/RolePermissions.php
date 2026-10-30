<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Tonyapps\B2b\Api\Data\RoleInterface;
use Tonyapps\B2b\Model\Permissions;

/**
 * Renders the stored permission list as readable labels in the roles grid.
 */
class RolePermissions extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly Permissions $permissions,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array<string, mixed> $dataSource
     * @return array<string, mixed>
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $labels = $this->permissions->getLabels();

        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            $granted = array_filter(array_map('trim', explode(',', (string)($item[$name] ?? ''))));
            $item[$name] = $granted
                ? implode(', ', array_intersect_key($labels, array_flip($granted)))
                : (string)__('Nothing beyond seeing the company');
        }

        return $dataSource;
    }
}
