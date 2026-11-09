<?php
declare(strict_types=1);

namespace Tonyapps\B2b\Test\Unit\View;

use PHPUnit\Framework\TestCase;

class EmailTemplatesTest extends TestCase
{
    /** @dataProvider templates */
    public function testCompanyEmailsUseTheStoreFrameAndAButton(string $file, ?string $urlVar): void
    {
        $html = (string) file_get_contents(__DIR__ . '/../../../view/frontend/email/' . $file);

        self::assertStringContainsString('design/email/header_template', $html);
        self::assertStringContainsString('design/email/footer_template', $html);
        self::assertStringContainsString('{{var company_name}}', $html);
        if ($urlVar !== null) {
            self::assertStringContainsString('<a href="{{var ' . $urlVar . '}}"', $html);
        }
    }

    public static function templates(): array
    {
        return [
            'new registration' => ['company_new.html', 'admin_url'],
            'approved' => ['company_approved.html', 'company_url'],
            'rejected' => ['company_rejected.html', null],
        ];
    }
}
