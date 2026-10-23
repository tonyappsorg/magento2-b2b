<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;

interface CompanyRepositoryInterface
{
    public function save(CompanyInterface $company): CompanyInterface;

    public function getById(int $companyId): CompanyInterface;

    public function delete(CompanyInterface $company): bool;

    public function deleteById(int $companyId): bool;

    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;
}
