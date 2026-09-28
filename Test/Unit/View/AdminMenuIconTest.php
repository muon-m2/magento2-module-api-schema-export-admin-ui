<?php

/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\ApiSchemaExportAdminUi\Test\Unit\View;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Source assertions on this module's icon in the MUON admin-menu hub.
 *
 * THE FAILURE MODE HERE IS SILENT. `Muon_AdminMenu` paints every hub group a generic `package`
 * glyph and expects each module to override it. A stylesheet that names the wrong `data-ui-id`, or
 * whose selector does not outrank that fallback, still compiles, deploys and renders — the group
 * simply keeps the generic glyph, and nothing is logged anywhere. That is exactly how this module
 * shipped with no icon of its own, so the contract is asserted from source rather than trusted.
 */
class AdminMenuIconTest extends TestCase
{
    private const MODULE = 'Muon_ApiSchemaExportAdminUi';

    private string $css;

    private string $layout;

    /**
     * The stylesheet reduced to what actually styles anything.
     *
     * BOTH removals are load-bearing. The inlined SVG payload contains the literal text `width`,
     * `height` and `stroke-width`, so a declaration assertion would match inside the glyph. And the
     * file's own comment block quotes the selector forms it is explaining — including the `> a`
     * shape it warns against and a sample `#nav li[data-ui-id=...]` — so a test read against the
     * raw file passes on prose while the stylesheet itself could be empty.
     *
     * @var string
     */
    private string $rules;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 3);
        $cssPath = $root . '/view/adminhtml/web/css/admin-menu.css';
        $layoutPath = $root . '/view/adminhtml/layout/default.xml';

        self::assertFileExists($cssPath, 'The module must ship its own hub icon stylesheet.');
        self::assertFileExists($layoutPath, 'The stylesheet must be registered in a layout file.');

        $this->css = (string) file_get_contents($cssPath);
        $this->layout = (string) file_get_contents($layoutPath);
        $this->rules = (string) preg_replace(
            ['~/\\*.*?\\*/~s', '/url\("data:[^"]*"\)/'],
            ['', 'url(<svg>)'],
            $this->css
        );
    }

    public function testTheStylesheetTargetsThisModulesHubGroup(): void
    {
        // Derived, not pasted. Muon_AdminMenu builds "Muon_AdminMenu::group_" plus the lowercased
        // module name, and Magento renders an id as a data-ui-id with every non-alphanumeric run
        // folded to "-". Naming it wrong costs nothing at runtime and shows no icon.
        $groupId = 'menu-Muon_AdminMenu::group_' . strtolower(self::MODULE);
        $expected = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $groupId));

        self::assertStringContainsString(
            '[data-ui-id="' . $expected . '"]',
            $this->rules,
            'The selector must name the exact group id the hub generates for ' . self::MODULE
        );
    }

    public function testTheSelectorOutranksTheHubsFallback(): void
    {
        // Magento emits <css> links in alphabetical module load order and Muon_AdminMenu sorts
        // before this module, so an equally specific selector LOSES to the fallback. The `li` type
        // qualifier is what wins it — see Muon_AdminMenu's developer guide.
        self::assertMatchesRegularExpression(
            '/#nav\s+li\[data-ui-id=/',
            $this->rules,
            'The selector needs a type qualifier (#nav li[data-ui-id=...]) to beat the hub fallback'
        );
    }

    public function testTheIconIsRegisteredInTheAdminLayout(): void
    {
        self::assertStringContainsString(
            self::MODULE . '::css/admin-menu.css',
            $this->layout,
            'An unregistered stylesheet is never emitted, and the group keeps the fallback glyph.'
        );
    }

    public function testItStylesTheGroupHeadingRatherThanAnAnchor(): void
    {
        // A level-1 group heading renders as <strong class="submenu-group-title">, never an <a>,
        // so a "> a:before" rule would match nothing at all and fail silently.
        self::assertStringContainsString('.submenu-group-title:before', $this->rules);
        self::assertStringNotContainsString('> a:before', $this->rules);
    }

    /**
     * Size, spacing and paint come from the hub's shared geometry rule, which already matches this
     * group through its prefix selector. Restating any of them is a copy that drifts the moment the
     * hub retunes its own.
     *
     * @param string $property
     * @return void
     */
    #[DataProvider('sharedGeometry')]
    public function testOnlyTheMaskImageIsDeclared(string $property): void
    {
        self::assertStringNotContainsString(
            $property,
            $this->rules,
            $property . " belongs to Muon_AdminMenu's shared rule, not to this module"
        );
    }

    /**
     * @return array<string,array<int,string>>
     */
    public static function sharedGeometry(): array
    {
        return [
            'mask-size' => ['mask-size'],
            'mask-position' => ['mask-position'],
            'mask-repeat' => ['mask-repeat'],
            'background-color' => ['background-color'],
            'content' => ['content:'],
            'height' => ['height:'],
            'width' => ['width:'],
        ];
    }
}
