<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Api\Data;

/**
 * A set of permissions inside one company.
 */
interface RoleInterface
{
    public const ROLE_ID = 'role_id';
    public const COMPANY_ID = 'company_id';
    public const ROLE_NAME = 'role_name';
    public const IS_DEFAULT = 'is_default';
    public const PERMISSIONS = 'permissions';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public function getId();

    public function getCompanyId(): ?int;

    public function setCompanyId(int $companyId): self;

    public function getRoleName(): ?string;

    public function setRoleName(string $roleName): self;

    public function isDefault(): bool;

    public function setIsDefault(bool $isDefault): self;

    /**
     * Permission resource IDs this role grants; anything absent is denied.
     *
     * @return string[]
     */
    public function getPermissions(): array;

    /**
     * @param string[] $permissions
     */
    public function setPermissions(array $permissions): self;

    public function hasPermission(string $permission): bool;
}
