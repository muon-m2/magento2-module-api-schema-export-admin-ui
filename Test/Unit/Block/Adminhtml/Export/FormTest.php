<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\ApiSchemaExportAdminUi\Test\Unit\Block\Adminhtml\Export;

use Magento\Backend\Block\Template\Context;
use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Phrase;
use Magento\Framework\UrlInterface;
use Muon\ApiSchemaExport\Api\RendererInterface;
use Muon\ApiSchemaExport\Api\RendererPoolInterface;
use Muon\ApiSchemaExportAdminUi\Block\Adminhtml\Export\Form;
use PHPUnit\Framework\TestCase;

/**
 * @see \Muon\ApiSchemaExportAdminUi\Block\Adminhtml\Export\Form
 */
class FormTest extends TestCase
{
    /**
     * Magento's backend Template constructor resolves its json and directory helpers through
     * ObjectManager::getInstance() rather than through the injected context, so a unit test has to
     * put an instance in place before constructing any backend block.
     *
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $jsonHelper = $this->createStub(JsonHelper::class);
        $directoryHelper = $this->createStub(DirectoryHelper::class);

        $objectManager = $this->createStub(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturnCallback(
            static fn (string $type) => $type === JsonHelper::class ? $jsonHelper : $directoryHelper
        );

        ObjectManager::setInstance($objectManager);
    }

    /**
     * Every registered renderer becomes a labelled checkbox choice.
     */
    public function testFormatsCarryCodeAndLabel(): void
    {
        $block = $this->makeBlock(
            ['openapi' => 'OpenAPI 3.1 schema', 'http' => 'HTTP client file (.http)'],
            []
        );

        self::assertSame(
            ['openapi' => 'OpenAPI 3.1 schema', 'http' => 'HTTP client file (.http)'],
            $block->getFormats()
        );
    }

    /**
     * The suggestion list is sorted, so the datalist is navigable.
     */
    public function testModuleNamesAreSorted(): void
    {
        $block = $this->makeBlock([], ['Muon_Zebra', 'Magento_Catalog', 'Muon_Alpha']);

        self::assertSame(['Magento_Catalog', 'Muon_Alpha', 'Muon_Zebra'], $block->getModuleNames());
    }

    /**
     * The form posts to the generate action.
     */
    public function testFormPostsToTheGenerateAction(): void
    {
        $block = $this->makeBlock([], []);

        self::assertSame(
            'https://muon.localhost/admin/muon_apischemaexport/export/generate',
            $block->getFormActionUrl()
        );
    }

    /**
     * A failed submission is restored into the form, then cleared so a later visit is blank.
     *
     * Without this the user re-types the selector and re-ticks every format after any validation
     * error, which is the difference between a usable screen and a hostile one.
     */
    public function testPersistedSubmissionIsRestoredThenCleared(): void
    {
        $persistor = $this->createMock(DataPersistorInterface::class);
        $persistor->method('get')->willReturn([
            'selectors' => 'Muon, Magento_Catalog*',
            'filename' => 'my-export',
            'formats' => ['http', 'swagger'],
            'yaml' => '1',
        ]);
        // Read once and dropped: leaving it behind would refill an unrelated later visit.
        $persistor->expects(self::once())->method('clear')->with(Form::FORM_DATA_KEY);

        $block = $this->makeBlock([], [], $persistor);

        self::assertSame('Muon, Magento_Catalog*', $block->getFieldValue('selectors'));
        self::assertSame('my-export', $block->getFieldValue('filename'));
        self::assertSame('', $block->getFieldValue('base_url'));
        self::assertTrue($block->isFormatSelected('http'));
        self::assertTrue($block->isFormatSelected('swagger'));
        self::assertFalse($block->isFormatSelected('openapi'));
        self::assertTrue($block->isFieldChecked('yaml'));
        self::assertFalse($block->isFieldChecked('split'));
    }

    /**
     * With nothing persisted the form opens on its default selection.
     */
    public function testFreshFormFallsBackToTheDefaultFormat(): void
    {
        $persistor = $this->createMock(DataPersistorInterface::class);
        $persistor->method('get')->willReturn(null);
        $persistor->expects(self::never())->method('clear');

        $block = $this->makeBlock([], [], $persistor);

        self::assertSame('', $block->getFieldValue('selectors'));
        self::assertTrue($block->isFormatSelected('openapi'));
        self::assertFalse($block->isFormatSelected('http'));
    }

    /**
     * @param array<string,string> $formats
     * @param string[] $moduleNames
     * @param \Magento\Framework\App\Request\DataPersistorInterface|null $persistor
     * @return \Muon\ApiSchemaExportAdminUi\Block\Adminhtml\Export\Form
     */
    private function makeBlock(array $formats, array $moduleNames, ?DataPersistorInterface $persistor = null): Form
    {
        $renderers = [];
        foreach ($formats as $code => $label) {
            $renderer = $this->createStub(RendererInterface::class);
            $renderer->method('getLabel')->willReturn(new Phrase($label));
            $renderers[$code] = $renderer;
        }

        $pool = $this->createStub(RendererPoolInterface::class);
        $pool->method('getAll')->willReturn($renderers);

        $moduleList = $this->createStub(ModuleListInterface::class);
        $moduleList->method('getNames')->willReturn($moduleNames);

        $urlBuilder = $this->createStub(UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturnCallback(
            static fn (string $route): string => 'https://muon.localhost/admin/' . $route
        );

        $context = $this->createStub(Context::class);
        $context->method('getUrlBuilder')->willReturn($urlBuilder);

        if ($persistor === null) {
            $persistor = $this->createStub(DataPersistorInterface::class);
            $persistor->method('get')->willReturn(null);
        }

        return new Form($context, $pool, $moduleList, $persistor);
    }
}
