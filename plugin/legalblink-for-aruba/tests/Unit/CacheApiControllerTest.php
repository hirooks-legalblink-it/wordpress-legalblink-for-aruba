<?php
/**
 * Unit tests for LBFA_Cache_API_Controller.
 *
 * Validates POST /cache/clear and GET/POST /cache/settings (read default,
 * persist new duration only when changed, side-effect: clearAll on change).
 */

declare(strict_types=1);

namespace LegalBlink\Tests\Unit;

use Brain\Monkey\Functions;
use LBFA_Cache_API_Controller;
use LBFA_Config_Helper;
use LBFA_Option_Helper;
use LBFA_Transient_Helper;
use LegalBlink\Tests\TestCase;
use Mockery;
use WP_REST_Request;

require_once dirname(__DIR__, 2) . '/classes/controller/api/class-lbfa-cache-api-controller.php';

class CacheApiControllerTest extends TestCase
{
    protected function set_up(): void
    {
        parent::set_up();

        LBFA_Option_Helper::reset();
        LBFA_Transient_Helper::reset();
        LBFA_Config_Helper::reset();

        Functions\when('__')->returnArg(1);
    }

    protected function tear_down(): void
    {
        Mockery::close();
        parent::tear_down();
    }

    public function testRegisterRoutesRegistersCacheEndpoints(): void
    {
        Functions\expect('register_rest_route')
            ->times(2)
            ->with('lbfa/v1', Mockery::anyOf('/cache/clear', '/cache/settings'), Mockery::type('array'));

        (new LBFA_Cache_API_Controller())->register_routes();
        $this->addToAssertionCount(1);
    }

    public function testClearCacheWipesTransientsAndReturnsSuccess(): void
    {
        LBFA_Transient_Helper::$__cache = ['x' => 1, 'y' => 2];

        $payload = (new LBFA_Cache_API_Controller())->clear_cache()->get_data();

        $this->assertTrue($payload['success']);
        $this->assertSame([], LBFA_Transient_Helper::$__cache);
    }

    public function testGetCacheSettingsReturnsDefaultWhenOptionMissing(): void
    {
        $request = new WP_REST_Request([], 'GET');
        $payload = (new LBFA_Cache_API_Controller())->handle_cache_settings($request)->get_data();

        $this->assertTrue($payload['success']);
        $this->assertSame(30, $payload['data']['cache_duration']);
    }

    public function testGetCacheSettingsReturnsStoredOption(): void
    {
        LBFA_Option_Helper::$__options['cache_duration'] = 60;
        $request = new WP_REST_Request([], 'GET');

        $payload = (new LBFA_Cache_API_Controller())->handle_cache_settings($request)->get_data();
        $this->assertSame(60, $payload['data']['cache_duration']);
    }

    public function testPostCacheSettingsPersistsNewDurationAndClearsCache(): void
    {
        LBFA_Option_Helper::$__options['cache_duration'] = 30;
        LBFA_Transient_Helper::$__cache = ['banner' => '<x>'];

        $request = new WP_REST_Request(['cache_duration' => 90], 'POST');
        $payload = (new LBFA_Cache_API_Controller())->handle_cache_settings($request)->get_data();

        $this->assertTrue($payload['success']);
        $this->assertSame(90, LBFA_Option_Helper::getOption('cache_duration'));
        $this->assertSame([], LBFA_Transient_Helper::$__cache);
    }

    public function testPostCacheSettingsKeepsCacheWhenDurationUnchanged(): void
    {
        LBFA_Option_Helper::$__options['cache_duration'] = 30;
        LBFA_Transient_Helper::$__cache = ['banner' => '<x>'];

        $request = new WP_REST_Request(['cache_duration' => 30], 'POST');
        (new LBFA_Cache_API_Controller())->handle_cache_settings($request);

        $this->assertSame(['banner' => '<x>'], LBFA_Transient_Helper::$__cache);
    }
}
