<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Ui\Component\MassAction;

use Magento\Framework\UrlInterface;
use Tonyapps\B2b\Model\ResourceModel\Company as CompanyResource;

/**
 * Sub-actions of the customer grid's "Assign to Company" mass action: one per company, plus an
 * entry that clears the membership.
 */
class CompanyOptions implements \JsonSerializable
{
    private const URL_PATH = 'tonyapps_b2b/customer/massAssignCompany';

    public function __construct(
        private readonly CompanyResource $companyResource,
        private readonly UrlInterface $urlBuilder
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function jsonSerialize(): array
    {
        $options = [];
        foreach ($this->companyResource->getCompanyOptions() as $companyId => $name) {
            $options[] = $this->option((int)$companyId, $name);
        }

        $options[] = $this->option(0, (string)__('— Remove from Company —'));

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    private function option(int $companyId, string $label): array
    {
        return [
            'type' => 'tonyapps_b2b_company_' . $companyId,
            'label' => $label,
            'url' => $this->urlBuilder->getUrl(self::URL_PATH, ['company_id' => $companyId]),
            // Grid mass-action labels are plain data, not knockout templates.
            '__disableTmpl' => true,
        ];
    }
}
