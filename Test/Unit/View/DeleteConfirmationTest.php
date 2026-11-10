<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\View;

use PHPUnit\Framework\TestCase;

class DeleteConfirmationTest extends TestCase
{
    /**
     * Removing a user and deleting a role ask first: core's data-post widget shows a confirm modal.
     *
     * @dataProvider templates
     */
    public function testDestructiveActionsAskForConfirmation(string $file, string $urlGetter): void
    {
        $template = (string) file_get_contents(__DIR__ . '/../../../view/frontend/templates/company/' . $file);

        self::assertMatchesRegularExpression(
            '/data-post=.*\'action\' => \$block->' . $urlGetter . '\(.*\'confirmation\' => true.*\'confirmationMessage\'/s',
            $template
        );
        self::assertStringNotContainsString('<form action="<?= $escaper->escapeUrl($block->' . $urlGetter, $template);
    }

    public static function templates(): array
    {
        return [
            'remove user' => ['users.phtml', 'getRemoveUrl'],
            'delete role' => ['roles.phtml', 'getDeleteUrl'],
        ];
    }
}
