<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Block\Adminhtml\Role\Edit;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Tonyapps\B2b\Api\Data\RoleInterface;
use Tonyapps\B2b\Api\RoleRepositoryInterface;

/**
 * Back to the roles grid of the company this role belongs to.
 */
class BackButton implements ButtonProviderInterface
{
    public function __construct(
        private readonly UrlInterface $urlBuilder,
        private readonly RequestInterface $request,
        private readonly RoleRepositoryInterface $roleRepository
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
            'on_click' => sprintf("location.href = '%s';", $this->getBackUrl()),
        ];
    }

    private function getBackUrl(): string
    {
        $companyId = (int)$this->request->getParam(RoleInterface::COMPANY_ID);

        if (!$companyId && $roleId = (int)$this->request->getParam(RoleInterface::ROLE_ID)) {
            try {
                $companyId = (int)$this->roleRepository->getById($roleId)->getCompanyId();
            } catch (\Exception) {
                $companyId = 0;
            }
        }

        return $this->urlBuilder->getUrl(
            'tonyapps_b2b/role/index',
            $companyId ? [RoleInterface::COMPANY_ID => $companyId] : []
        );
    }
}
