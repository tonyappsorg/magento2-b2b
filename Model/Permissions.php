<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model;

/**
 * The catalogue of things a company role can grant.
 *
 * ponytail: a flat list of constants rather than a tree in etc/acl.xml. Magento's ACL is the
 * *admin* permission tree — putting storefront company permissions in it would show them to every
 * administrator editing an admin role, which is not what they are. The list is one level deep, so
 * a tree buys nothing.
 *
 * Every permission here is enforced by code that exists — a toggle that does nothing is worse
 * than no toggle.
 */
class Permissions
{
    public const PROFILE_VIEW = 'profile_view';
    public const PROFILE_EDIT = 'profile_edit';
    public const USERS_VIEW = 'users_view';
    public const USERS_MANAGE = 'users_manage';
    public const ROLES_VIEW = 'roles_view';
    public const ROLES_MANAGE = 'roles_manage';
    public const CHILDREN_VIEW = 'children_view';
    public const CHILDREN_MANAGE = 'children_manage';
    public const ORDERS_VIEW_COMPANY = 'orders_view_company';
    public const CREDIT_VIEW = 'credit_view';
    // The planned "place_order", narrowed to what is shared: members still check out with their own card.
    public const CREDIT_USE = 'credit_use';
    public const REQUISITION_LISTS_VIEW = 'requisition_lists_view';
    public const REQUISITION_LISTS_MANAGE = 'requisition_lists_manage';

    /**
     * The one permission a company member always has: without it they could not see the company
     * page that tells them they are in a company.
     */
    public const ALWAYS_GRANTED = self::PROFILE_VIEW;

    /**
     * Permissions that are meaningless without another one, so ticking them ticks its parent too.
     *
     * @var array<string, string>
     */
    private const IMPLIES = [
        self::PROFILE_EDIT => self::PROFILE_VIEW,
        self::USERS_MANAGE => self::USERS_VIEW,
        self::ROLES_MANAGE => self::ROLES_VIEW,
        self::CHILDREN_MANAGE => self::CHILDREN_VIEW,
        self::CREDIT_USE => self::CREDIT_VIEW,
        self::REQUISITION_LISTS_MANAGE => self::REQUISITION_LISTS_VIEW,
    ];

    /**
     * Permissions grouped for display, in the order the forms should show them.
     *
     * @return array<string, array<string, \Magento\Framework\Phrase>>
     */
    public function getGrouped(): array
    {
        return [
            (string)__('Company Profile') => [
                self::PROFILE_VIEW => __('View the company profile'),
                self::PROFILE_EDIT => __('Edit the company profile'),
            ],
            (string)__('Users') => [
                self::USERS_VIEW => __('See the company users'),
                self::USERS_MANAGE => __('Add and remove company users'),
            ],
            (string)__('Roles') => [
                self::ROLES_VIEW => __('See the company roles'),
                self::ROLES_MANAGE => __('Create and change company roles'),
            ],
            (string)__('Child Companies') => [
                self::CHILDREN_VIEW => __('See the child companies'),
                self::CHILDREN_MANAGE => __('Create and change child companies'),
            ],
            (string)__('Orders') => [
                self::ORDERS_VIEW_COMPANY => __('See orders placed by everyone in the company'),
            ],
            (string)__('Store Credit') => [
                self::CREDIT_VIEW => __('See the store credit balance and history'),
                self::CREDIT_USE => __('Pay with the company\'s store credit'),
            ],
            (string)__('Requisition Lists') => [
                self::REQUISITION_LISTS_VIEW => __('View and add company requisition lists to the cart'),
                self::REQUISITION_LISTS_MANAGE => __('Create, edit, and delete company requisition lists'),
            ],
        ];
    }

    /**
     * @return string[]
     */
    public function getAll(): array
    {
        return array_merge(...array_map('array_keys', array_values($this->getGrouped())));
    }

    public function exists(string $permission): bool
    {
        return in_array($permission, $this->getAll(), true);
    }

    /**
     * Drop anything unknown and add the permissions the chosen ones depend on.
     *
     * Saving "edit the profile" without "view the profile" would produce a role that cannot reach
     * the page it is allowed to change, so the dependency is added rather than rejected.
     *
     * @param string[] $permissions
     * @return string[]
     */
    public function normalise(array $permissions): array
    {
        $granted = array_values(array_intersect($this->getAll(), $permissions));

        foreach (self::IMPLIES as $permission => $required) {
            if (in_array($permission, $granted, true) && !in_array($required, $granted, true)) {
                $granted[] = $required;
            }
        }

        // Keep catalogue order so a stored role always reads the same way.
        return array_values(array_intersect($this->getAll(), $granted));
    }
}
