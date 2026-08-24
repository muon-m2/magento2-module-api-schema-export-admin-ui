<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\ApiSchemaExportAdminUi\Test\Unit\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\InputException;
use Muon\ApiSchemaExport\Api\Data\RenderContextInterface;
use Muon\ApiSchemaExport\Api\RendererPoolInterface;
use Muon\ApiSchemaExportAdminUi\Model\ExportRequestBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @see \Muon\ApiSchemaExportAdminUi\Model\ExportRequestBuilder
 */
class ExportRequestBuilderTest extends TestCase
{
    /**
     * @var ExportRequestBuilder
     */
    private ExportRequestBuilder $builder;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $pool = $this->createStub(RendererPoolInterface::class);
        $pool->method('getCodes')->willReturn(['http', 'openapi', 'postman', 'swagger']);

        $this->builder = new ExportRequestBuilder($pool);
    }

    /**
     * A complete form submission maps onto the request.
     */
    public function testCompleteSubmissionMapsOntoTheRequest(): void
    {
        $request = $this->builder->createFromRequest($this->makeHttpRequest([
            'selectors' => 'Muon, Magento_Catalog*',
            'formats' => ['openapi', 'postman'],
            'filename' => 'my-export',
            'base_url' => 'https://api.example.test',
            'yaml' => '1',
            'split' => '1',
        ]));

        self::assertSame(['Muon', 'Magento_Catalog*'], $request->getSelectors());
        self::assertSame(['openapi', 'postman'], $request->getFormats());
        self::assertSame('my-export', $request->getBaseFilename());
        self::assertSame('https://api.example.test', $request->getBaseUrl());
        self::assertSame(RenderContextInterface::FORMAT_YAML, $request->getSerialization());
        self::assertTrue($request->isSplitByModule());
        self::assertTrue($request->isIncludeAsync());
    }

    /**
     * Async is included unless the form asks otherwise, matching the CLI default.
     */
    public function testAsyncIsIncludedUnlessSuppressed(): void
    {
        $included = $this->builder->createFromRequest($this->makeHttpRequest([
            'selectors' => 'Muon',
            'formats' => ['openapi'],
        ]));
        $excluded = $this->builder->createFromRequest($this->makeHttpRequest([
            'selectors' => 'Muon',
            'formats' => ['openapi'],
            'no_async' => '1',
        ]));

        self::assertTrue($included->isIncludeAsync());
        self::assertFalse($excluded->isIncludeAsync());
    }

    /**
     * Blank optional fields become null rather than empty strings.
     */
    public function testBlankOptionalFieldsBecomeNull(): void
    {
        $request = $this->builder->createFromRequest($this->makeHttpRequest([
            'selectors' => 'Muon',
            'formats' => ['openapi'],
            'filename' => '   ',
            'base_url' => '',
        ]));

        self::assertNull($request->getBaseFilename());
        self::assertNull($request->getBaseUrl());
    }

    /**
     * An empty selector field is rejected before any work is done.
     */
    public function testEmptySelectorsAreRejected(): void
    {
        $this->expectException(InputException::class);
        $this->expectExceptionMessage('module selector');

        $this->builder->createFromRequest($this->makeHttpRequest([
            'selectors' => '  ,  ',
            'formats' => ['openapi'],
        ]));
    }

    /**
     * No format selected is rejected.
     */
    public function testMissingFormatsAreRejected(): void
    {
        $this->expectException(InputException::class);
        $this->expectExceptionMessage('output format');

        $this->builder->createFromRequest($this->makeHttpRequest(['selectors' => 'Muon']));
    }

    /**
     * An unknown format code is rejected against the pool, never used to resolve a class.
     */
    public function testUnknownFormatIsRejected(): void
    {
        $this->expectException(InputException::class);
        $this->expectExceptionMessage('evil');

        $this->builder->createFromRequest($this->makeHttpRequest([
            'selectors' => 'Muon',
            'formats' => ['evil'],
        ]));
    }

    /**
     * A scalar posted where an array is expected does not become a format.
     */
    public function testNonArrayFormatsParameterIsRejected(): void
    {
        $this->expectException(InputException::class);

        $this->builder->createFromRequest($this->makeHttpRequest([
            'selectors' => 'Muon',
            'formats' => 'openapi',
        ]));
    }

    /**
     * Build a request stub returning the given parameters.
     *
     * @param array<string,mixed> $params
     * @return \Magento\Framework\App\RequestInterface
     */
    private function makeHttpRequest(array $params): RequestInterface
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            static fn (string $key, $default = null) => $params[$key] ?? $default
        );

        return $request;
    }
}
