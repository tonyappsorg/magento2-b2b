<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model;

use Magento\Framework\Model\AbstractModel;
use Tonyapps\B2b\Api\Data\RoleInterface;

/**
 * A company role.
 *
 * Permissions are a comma-separated list in one column rather than a join table: there are a
 * handful of permissions, a handful of roles per company, and nothing ever asks "which roles
 * grant X". Presence means allowed, absence means denied — there is no explicit deny.
 */
class Role extends AbstractModel implements RoleInterface
{
    protected function _construct()
    {
        $this->_init(ResourceModel\Role::class);
    }

    public function getCompanyId(): ?int
    {
        $companyId = $this->getData(self::COMPANY_ID);

        return $companyId === null || $companyId === '' ? null : (int)$companyId;
    }

    public function setCompanyId(int $companyId): RoleInterface
    {
        return $this->setData(self::COMPANY_ID, $companyId);
    }

    public function getRoleName(): ?string
    {
        $name = $this->getData(self::ROLE_NAME);

        return $name === null ? null : (string)$name;
    }

    public function setRoleName(string $roleName): RoleInterface
    {
        return $this->setData(self::ROLE_NAME, $roleName);
    }

    public function isDefault(): bool
    {
        return (bool)$this->getData(self::IS_DEFAULT);
    }

    public function setIsDefault(bool $isDefault): RoleInterface
    {
        return $this->setData(self::IS_DEFAULT, $isDefault ? 1 : 0);
    }

    public function getPermissions(): array
    {
        $permissions = $this->getData(self::PERMISSIONS);

        if (is_array($permissions)) {
            return array_values(array_filter($permissions, static fn($p): bool => $p !== '' && $p !== null));
        }

        if ($permissions === null || $permissions === '') {
            return [];
        }

        return array_values(
            array_filter(
                array_map('trim', explode(',', (string)$permissions)),
                static fn(string $p): bool => $p !== ''
            )
        );
    }

    public function setPermissions(array $permissions): RoleInterface
    {
        return $this->setData(self::PERMISSIONS, implode(',', $permissions));
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->getPermissions(), true);
    }

    /**
     * @return $this
     */
    public function beforeSave()
    {
        parent::beforeSave();

        if (is_array($this->getData(self::PERMISSIONS))) {
            $this->setData(self::PERMISSIONS, implode(',', $this->getPermissions()));
        }

        return $this;
    }
}
