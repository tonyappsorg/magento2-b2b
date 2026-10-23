<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Tonyapps\B2b\Api\Data\CompanyInterface;

/**
 * Edit / delete action links for the companies grid.
 */
class CompanyActions extends Column
{
    private const URL_PATH_EDIT = 'tonyapps_b2b/company/edit';
    private const URL_PATH_DELETE = 'tonyapps_b2b/company/delete';
    private const URL_PATH_ROLES = 'tonyapps_b2b/role/index';
    private const URL_PATH_CREDIT = 'tonyapps_b2b/credit/index';

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
            if (!isset($item[CompanyInterface::ENTITY_ID])) {
                continue;
            }

            $companyId = (int)$item[CompanyInterface::ENTITY_ID];
            $item[$name]['edit'] = [
                'href' => $this->urlBuilder->getUrl(
                    self::URL_PATH_EDIT,
                    [CompanyInterface::ENTITY_ID => $companyId]
                ),
                'label' => __('Edit'),
            ];
            $item[$name]['roles'] = [
                'href' => $this->urlBuilder->getUrl(
                    self::URL_PATH_ROLES,
                    ['company_id' => $companyId]
                ),
                'label' => __('Roles'),
            ];
            $item[$name]['credit'] = [
                'href' => $this->urlBuilder->getUrl(self::URL_PATH_CREDIT, ['company_id' => $companyId]),
                'label' => __('Credit'),
            ];
            $item[$name]['delete'] = [
                'href' => $this->urlBuilder->getUrl(
                    self::URL_PATH_DELETE,
                    [CompanyInterface::ENTITY_ID => $companyId]
                ),
                'label' => __('Delete'),
                'confirm' => [
                    'title' => __('Delete "%1"', $item[CompanyInterface::NAME] ?? ''),
                    'message' => __('Are you sure you want to delete this company? Its users stay, but lose their company pricing.'),
                ],
                'post' => true,
            ];
        }

        return $dataSource;
    }
}
