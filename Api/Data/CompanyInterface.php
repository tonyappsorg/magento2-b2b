<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Api\Data;

/**
 * B2B company entity.
 */
interface CompanyInterface
{
    public const ENTITY_ID = 'entity_id';
    public const PARENT_ID = 'parent_id';
    public const PATH = 'path';
    public const LEVEL = 'level';
    public const NAME = 'name';
    public const LEGAL_NAME = 'legal_name';
    public const VAT_TAX_ID = 'vat_tax_id';
    public const EMAIL = 'email';
    public const TELEPHONE = 'telephone';
    public const STREET = 'street';
    public const CITY = 'city';
    public const REGION_ID = 'region_id';
    public const REGION = 'region';
    public const POSTCODE = 'postcode';
    public const COUNTRY_ID = 'country_id';
    public const STATUS = 'status';
    public const CUSTOMER_GROUP_ID = 'customer_group_id';
    public const ADMIN_CUSTOMER_ID = 'admin_customer_id';
    public const PAYMENT_MODE = 'payment_mode';
    public const CREDIT_BALANCE = 'credit_balance';
    public const COMMENT = 'comment';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public const STATUS_PENDING = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_REJECTED = 2;
    public const STATUS_BLOCKED = 3;

    /**
     * Payment restriction modes.
     */
    public const PAYMENT_MODE_INHERIT = 0;
    public const PAYMENT_MODE_ALL = 1;
    public const PAYMENT_MODE_RESTRICT = 2;

    /**
     * Separator between IDs in the materialized path.
     */
    public const PATH_SEPARATOR = '/';

    public function getId();

    public function getParentId(): ?int;

    public function setParentId(?int $parentId): self;

    /**
     * Materialized path of ancestor IDs ending with this company's own ID, e.g. "1/4/9".
     */
    public function getPath(): ?string;

    /**
     * Ancestor IDs from root down to (and including) this company.
     *
     * @return int[]
     */
    public function getPathIds(): array;

    /**
     * ID of the root company this one belongs to; own ID when this company is a root.
     */
    public function getRootId(): ?int;

    public function getLevel(): int;

    public function getName(): ?string;

    public function setName(string $name): self;

    public function getStatus(): int;

    public function setStatus(int $status): self;

    public function isActive(): bool;

    /**
     * Customer group set directly on this company; NULL means "inherit from the parent".
     */
    public function getCustomerGroupId(): ?int;

    public function setCustomerGroupId(?int $groupId): self;

    public function getAdminCustomerId(): ?int;

    /**
     * How this company decides which payment methods its people may use.
     */
    public function getPaymentMode(): int;

    public function setPaymentMode(int $paymentMode): self;

    public function setAdminCustomerId(?int $customerId): self;
}
