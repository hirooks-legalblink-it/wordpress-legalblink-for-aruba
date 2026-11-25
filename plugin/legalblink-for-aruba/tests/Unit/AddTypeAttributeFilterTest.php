<?php

declare(strict_types=1);

namespace LegalBlink\Tests\Unit;

use LegalBlink\Tests\TestCase;

/**
 * lbfa_add_type_attribute() runs on the global `wp_script_attributes` filter,
 * so it sees every script tag printed through wp_get_script_tag(), not only
 * the plugin's own. The filter contract does not guarantee an `id` key:
 * WordPress 7.1 prints the concatenated admin scripts (load-scripts.php)
 * without one, and so can themes and other plugins (S#8016).
 */
class AddTypeAttributeFilterTest extends TestCase
{
    protected function set_up(): void
    {
        parent::set_up();
        // The main file registers hooks when loaded: Brain Monkey must be active.
        require_once dirname(__DIR__, 2) . '/legalblink-for-aruba.php';
    }

    public function testAddsModuleTypeToAdminUiScript(): void
    {
        $attributes = lbfa_add_type_attribute([
            'src' => 'https://example.test/wp-content/plugins/legalblink-for-aruba/assets/admin-ui/index.js',
            'id' => 'lbfa_admin-ui-main-script-js',
        ]);

        $this->assertSame('module', $attributes['type']);
    }

    public function testLeavesOtherScriptsUnchanged(): void
    {
        $original = [
            'src' => 'https://example.test/wp-includes/js/jquery/jquery.min.js',
            'id' => 'jquery-core-js',
        ];

        $this->assertSame($original, lbfa_add_type_attribute($original));
    }

    public function testLeavesScriptsWithoutIdUnchanged(): void
    {
        $original = [
            'src' => 'https://example.test/wp-admin/load-scripts.php?c=0&load%5Bchunk_0%5D=jquery-core&ver=7.1.5',
        ];

        $this->assertSame($original, lbfa_add_type_attribute($original));
    }
}
