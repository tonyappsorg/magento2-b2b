<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model;

use Magento\Framework\Model\AbstractModel;
use Tonyapps\B2b\Api\Data\CompanyInterface;

/**
 * B2B company.
 *
 * Hierarchy is stored as a materialized path of entity IDs (category style) so "my root" and
 * "my subtree" are single queries rather than recursion. The path is maintained by the resource
 * model; nothing else should write to it.
 */
class Company extends AbstractModel implements CompanyInterface
{
    /** @param array<string, mixed> $data */
    public function applyCustomerData(array $data): CompanyInterface
    {
        foreach (CompanyInterface::CUSTOMER_EDITABLE_FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $value = is_array($data[$field])
                ? implode("\n", array_filter(array_map('trim', $data[$field])))
                : trim((string)$data[$field]);
            $this->setData($field, $value === '' ? null : $value);
        }

        $this->setData(CompanyInterface::NAME, trim((string)($data[CompanyInterface::NAME] ?? '')));

        return $this;
    }

    protected function _construct()
    {
        $this->_init(ResourceModel\Company::class);
    }

    public function setParentId(?int $parentId): CompanyInterface
    {
        return $this->setData(self::PARENT_ID, $parentId);
    }

    public function getPath(): ?string
    {
        $path = $this->getData(self::PATH);

        return $path === null || $path === '' ? null : (string)$path;
    }

    public function getPathIds(): array
    {
        $path = $this->getPath();

        return $path === null ? [] : array_map('intval', explode(self::PATH_SEPARATOR, $path));
    }

    public function getRootId(): ?int
    {
        return $this->getPathIds()[0] ?? ($this->getId() === null ? null : (int)$this->getId());
    }

    public function getLevel(): int
    {
        return (int)$this->getData(self::LEVEL);
    }

    public function getName(): ?string
    {
        $name = $this->getData(self::NAME);

        return $name === null ? null : (string)$name;
    }

    public function setName(string $name): CompanyInterface
    {
        return $this->setData(self::NAME, $name);
    }

    public function getStatus(): int
    {
        return (int)$this->getData(self::STATUS);
    }

    public function setStatus(int $status): CompanyInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getCustomerGroupId(): ?int
    {
        $groupId = $this->getData(self::CUSTOMER_GROUP_ID);

        return $groupId === null || $groupId === '' ? null : (int)$groupId;
    }

    public function setCustomerGroupId(?int $groupId): CompanyInterface
    {
        return $this->setData(self::CUSTOMER_GROUP_ID, $groupId);
    }

    public function getAdminCustomerId(): ?int
    {
        $customerId = $this->getData(self::ADMIN_CUSTOMER_ID);

        return $customerId === null || $customerId === '' ? null : (int)$customerId;
    }

    public function setAdminCustomerId(?int $customerId): CompanyInterface
    {
        return $this->setData(self::ADMIN_CUSTOMER_ID, $customerId);
    }

    /**
     * Blank out optional fields that arrive as an empty string so they store as NULL.
     *
     * @return $this
     */
    public function beforeSave()
    {
        parent::beforeSave();

        foreach ([self::PARENT_ID, self::CUSTOMER_GROUP_ID, self::ADMIN_CUSTOMER_ID, self::REGION_ID] as $field) {
            if ($this->getData($field) === '') {
                $this->setData($field, null);
            }
        }

        if (is_array($this->getData(self::STREET))) {
            $this->setData(self::STREET, implode("\n", array_filter($this->getData(self::STREET))));
        }

        return $this;
    }
}
