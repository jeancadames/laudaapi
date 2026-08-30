<?php

namespace Tests\Unit\Ui;

use PHPUnit\Framework\TestCase;

class GlobalFlashToastsContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = dirname(__DIR__, 3);
    }

    public function test_backend_shares_all_global_flash_levels(): void
    {
        $source = $this->read('app/Http/Middleware/HandleInertiaRequests.php');

        foreach (['success', 'warning', 'error', 'info'] as $level) {
            $this->assertStringContainsString("'{$level}'", $source);
            $this->assertStringContainsString("session()->get('{$level}')", $source);
        }
    }

    public function test_global_flash_host_is_mounted_once_at_inertia_root(): void
    {
        $app = $this->read('resources/js/app.ts');
        $global = $this->read('resources/js/components/GlobalFlashToasts.vue');

        $this->assertStringContainsString(
            "import GlobalFlashToasts from './components/GlobalFlashToasts.vue';",
            $app
        );
        $this->assertStringContainsString('h(Fragment', $app);
        $this->assertStringContainsString('h(GlobalFlashToasts)', $app);
        $this->assertSame(
            1,
            substr_count($app, 'h(GlobalFlashToasts)')
        );

        $this->assertStringContainsString('<Toaster />', $global);
        $this->assertStringContainsString("router.on('success'", $global);
        $this->assertStringContainsString('event.detail.page.props.flash', $global);
        $this->assertStringContainsString('showFlash(page.props.flash)', $global);
    }

    public function test_success_warning_error_and_info_have_distinct_toast_contracts(): void
    {
        $global = $this->read('resources/js/components/GlobalFlashToasts.vue');
        $variants = $this->read('resources/js/components/ui/toast/index.ts');
        $types = $this->read('resources/js/types/inertia.d.ts');

        foreach (['success', 'warning', 'error', 'info'] as $level) {
            $this->assertStringContainsString($level, $global);
            $this->assertStringContainsString($level, $types);
        }

        foreach (['success:', 'warning:', 'info:', 'destructive:'] as $variant) {
            $this->assertStringContainsString($variant, $variants);
        }
    }

    public function test_duplicate_page_level_flash_toasts_are_removed(): void
    {
        $files = [
            'resources/js/layouts/MarketingLayout.vue',
            'resources/js/pages/Admin/DiagnosisRequests/Show.vue',
            'resources/js/pages/Subscriber/Support/Show.vue',
            'resources/js/pages/Subscriber/Support/Index.vue',
            'resources/js/pages/Subscriber/PaymentMethods/Index.vue',
            'resources/js/pages/Subscriber/Services/My.vue',
            'resources/js/pages/Subscriber/Services/Category.vue',
            'resources/js/pages/LaudaERP/Support/Show.vue',
            'resources/js/pages/LaudaERP/Support/Index.vue',
        ];

        foreach ($files as $file) {
            $source = $this->read($file);

            foreach ([
                'flashError',
                'flashSuccess',
                'lastFlashKey',
                'reviewToast',
                'showReviewToast',
                'page.props.flash',
            ] as $legacy) {
                $this->assertStringNotContainsString(
                    $legacy,
                    $source,
                    "{$file} still contains local flash consumer {$legacy}"
                );
            }
        }

        $marketing = $this->read('resources/js/layouts/MarketingLayout.vue');
        $this->assertStringNotContainsString('<Toaster />', $marketing);
    }

    public function test_local_non_flash_toasts_are_preserved(): void
    {
        foreach ([
            'resources/js/pages/Subscriber/Support/Index.vue',
            'resources/js/pages/Subscriber/Services/My.vue',
            'resources/js/pages/Subscriber/Services/Category.vue',
            'resources/js/pages/LaudaERP/Support/Index.vue',
        ] as $file) {
            $source = $this->read($file);

            $this->assertStringContainsString('useToast', $source);
            $this->assertStringContainsString('toast({', $source);
        }
    }

    public function test_field_validation_remains_local_to_forms(): void
    {
        $admin = $this->read('resources/js/pages/Admin/DiagnosisRequests/Show.vue');

        $this->assertStringContainsString('publishForm.setError(errors)', $admin);
        $this->assertStringNotContainsString('showReviewToast', $admin);
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($this->root.'/'.$path);

        $this->assertNotFalse($contents, "Unable to read {$path}");

        return (string) $contents;
    }
}
