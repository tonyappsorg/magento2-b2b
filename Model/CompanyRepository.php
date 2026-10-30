<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Tonyapps\B2b\Api\CompanyRepositoryInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Model\ResourceModel\Company as CompanyResource;

class CompanyRepository implements CompanyRepositoryInterface
{
    public function __construct(
        private readonly CompanyResource $resource,
        private readonly CompanyFactory $companyFactory,
        private readonly CustomerGroupSync $customerGroupSync
    ) {
    }

    public function save(CompanyInterface $company): CompanyInterface
    {
        try {
            /** @var Company $company */
            $this->resource->save($company);
        } catch (CouldNotSaveException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the company: %1', $exception->getMessage()),
                $exception
            );
        }

        return $company;
    }

    public function getById(int $companyId): CompanyInterface
    {
        /** @var Company $company */
        $company = $this->companyFactory->create();
        $this->resource->load($company, $companyId);
        if (!$company->getId()) {
            throw new NoSuchEntityException(__('Company with id "%1" does not exist.', $companyId));
        }

        return $company;
    }

    public function delete(CompanyInterface $company): bool
    {
        $companyId = (int)$company->getId();
        if ($companyId && $this->resource->hasChildren($companyId)) {
            throw new CouldNotDeleteException(
                __('This company has child companies. Re-assign or delete them first.')
            );
        }
        if ($companyId && $this->resource->hasCreditActivity($companyId)) {
            // Deleting would cascade away the ledger, and refunds of its credit-paid orders would find no pool.
            throw new CouldNotDeleteException(__(
                'This company has used store credit, so it can\'t be deleted. Block it instead.'
            ));
        }

        // Take the company's group off its members while the membership rows still exist — they
        // go with the company (foreign key), and afterwards there is no way to tell who was in it.
        if ($companyId) {
            $this->customerGroupSync->releaseCompanyTree($companyId);
        }

        try {
            /** @var Company $company */
            $this->resource->delete($company);
        } catch (CouldNotDeleteException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete the company: %1', $exception->getMessage()),
                $exception
            );
        }

        return true;
    }

    public function deleteById(int $companyId): bool
    {
        return $this->delete($this->getById($companyId));
    }

}
