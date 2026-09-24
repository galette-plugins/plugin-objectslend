<?php

/**
 * This file is part of Galette Objects Lend plugin (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2013-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteObjectsLend\Controllers\tests\units;

use Galette\Tests\GaletteRoutingTestCase;
use GaletteObjectsLend\LendPreferences;

/**
 * Main controller tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MainController extends GaletteRoutingTestCase
{
    protected int $seed = 20260924101512;
    protected bool $load_plugins = true;

    /** @var array<string,mixed> */
    private array $orig_prefs;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->orig_prefs = [];
        foreach (array_keys(LendPreferences::getSchema()) as $name) {
            $this->orig_prefs[$name] = $this->preferences->getPluginValue($name);
        }
    }

    /**
     * Cleanup after each test method
     */
    public function tearDown(): void
    {
        foreach ($this->orig_prefs as $name => $value) {
            $this->preferences->setValue($name, $value, $this->login);
        }
        $this->login->logout();
        parent::tearDown();
    }

    /**
     * Preferences page shows stored values
     */
    public function testPreferences(): void
    {
        $this->logSuperAdmin();
        $this->assertTrue($this->preferences->setValue(LendPreferences::THUMB_MAX_WIDTH, 321, $this->login));

        $test_response = $this->app->handle($this->createRequest(route_name: 'objectslend_preferences'));
        $this->assertSame(200, $test_response->getStatusCode());
        $body = (string)$test_response->getBody();
        $this->assertStringContainsString('name="pref_objectslend_thumb_max_width"', $body);
        $this->assertStringContainsString('value="321"', $body);
        $this->assertStringContainsString('name="pref_objectslend_view_serial"', $body);
    }

    /**
     * Store preferences: unchecked boxes are off, unknown names are ignored
     */
    public function testStorePreferences(): void
    {
        $this->logSuperAdmin();
        $request = $this->createRequest(route_name: 'store_objectlend_preferences', method: 'POST')
            ->withParsedBody([
                LendPreferences::VIEW_SERIAL => '1',
                LendPreferences::GENERATED_CONTRIBUTION_TYPE_ID => '3',
                LendPreferences::GENERATED_CONTRIB_INFO_TEXT => ' Rent of {NAME} ',
                LendPreferences::THUMB_MAX_WIDTH => '200',
                'pref_nom' => 'Hijacked',
            ]);
        $test_response = $this->app->handle($request);
        $this->assertSame(302, $test_response->getStatusCode());
        $this->assertSame(
            [$this->routeparser->urlFor('objectslend_preferences')],
            $test_response->getHeader('Location')
        );
        $this->expectFlashData(['success_detected' => ['Preferences have been successfully stored!']]);

        $lendsprefs = new LendPreferences($this->preferences);
        $this->assertTrue($lendsprefs->isEnabled(LendPreferences::VIEW_SERIAL));
        //defaults on, but not posted
        $this->assertFalse($lendsprefs->isEnabled(LendPreferences::ENABLE_MEMBER_RENT_OBJECT));
        $this->assertFalse($lendsprefs->imagesInLists());
        $this->assertSame(3, $lendsprefs->getContributionTypeId());
        $this->assertSame('Rent of {NAME}', $lendsprefs->getContributionText());
        $this->assertSame(200, $lendsprefs->getThumbWidth());
        //not posted: kept
        $this->assertSame($this->orig_prefs[LendPreferences::THUMB_MAX_HEIGHT], $lendsprefs->getThumbHeight());
        $this->assertNotSame('Hijacked', $this->preferences->pref_nom);
    }

    /**
     * Invalid value is reported
     */
    public function testStoreInvalidPreference(): void
    {
        $this->logSuperAdmin();
        $request = $this->createRequest(route_name: 'store_objectlend_preferences', method: 'POST')
            ->withParsedBody([LendPreferences::THUMB_MAX_WIDTH => '0']);
        $test_response = $this->app->handle($request);
        $this->assertSame(302, $test_response->getStatusCode());
        //an invalid value is not stored
        $this->preferences->load();
        $this->assertNotSame(0, (new LendPreferences($this->preferences))->getThumbWidth());

        $flash = $this->flash_data['slimFlash'] ?? [];
        $this->assertArrayNotHasKey('success_detected', $flash);
        $this->assertArrayHasKey('error_detected', $flash);
        $this->flash_data = [];
    }

    /**
     * Preferences are for admins only
     */
    public function testStorePreferencesAsMember(): void
    {
        $mdata = $this->dataAdherentOne();
        $this->getMemberOne();
        $this->assertTrue($this->login->login($mdata['login_adh'], $mdata['mdp_adh']));
        $request = $this->createRequest(route_name: 'store_objectlend_preferences', method: 'POST')
            ->withParsedBody([LendPreferences::THUMB_MAX_WIDTH => '222']);
        $this->app->handle($request);
        $this->assertNotSame(222, (new LendPreferences($this->preferences))->getThumbWidth());
    }
}
