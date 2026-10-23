<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Tonyapps\B2b\Model\Permissions;

/**
 * The permission catalogue as grouped checkbox options for the admin role form.
 */
class PermissionOptions implements OptionSourceInterface
{
    public function __construct(
        private readonly Permissions $permissions
    ) {
    }

    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase|string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->permissions->getGrouped() as $groupLabel => $groupPermissions) {
            foreach ($groupPermissions as $permission => $label) {
                $options[] = [
                    'value' => $permission,
                    'label' => sprintf('%s: %s', $groupLabel, $label),
                ];
            }
        }

        return $options;
    }
}
