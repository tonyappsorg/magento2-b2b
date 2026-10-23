<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Tonyapps\B2b\Api\Data\RoleInterface;

/**
 * Edit / delete links for the admin roles grid.
 */
class RoleActions extends Column
{
    private const URL_PATH_EDIT = 'tonyapps_b2b/role/edit';
    private const URL_PATH_DELETE = 'tonyapps_b2b/role/delete';

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
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

        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item[RoleInterface::ROLE_ID])) {
                continue;
            }

            $roleId = (int)$item[RoleInterface::ROLE_ID];
            $item[$name]['edit'] = [
                'href' => $this->urlBuilder->getUrl(self::URL_PATH_EDIT, [RoleInterface::ROLE_ID => $roleId]),
                'label' => __('Edit'),
            ];
            $item[$name]['delete'] = [
                'href' => $this->urlBuilder->getUrl(self::URL_PATH_DELETE, [RoleInterface::ROLE_ID => $roleId]),
                'label' => __('Delete'),
                'confirm' => [
                    'title' => __('Delete "%1"', $item[RoleInterface::ROLE_NAME] ?? ''),
                    'message' => __('Are you sure you want to delete this role?'),
                ],
                'post' => true,
            ];
        }

        return $dataSource;
    }
}
