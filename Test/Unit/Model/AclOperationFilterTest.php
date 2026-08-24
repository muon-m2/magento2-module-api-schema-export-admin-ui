<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\ApiSchemaExportAdminUi\Test\Unit\Model;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Reflection\TypeProcessor;
use Muon\ApiSchemaExport\Api\Data\ApiSurfaceInterface;
use Muon\ApiSchemaExport\Api\Data\OperationInterface;
use Muon\ApiSchemaExport\Model\Data\ApiSurface;
use Muon\ApiSchemaExport\Model\Data\Operation;
use Muon\ApiSchemaExport\Model\Export\SurfaceSplitter;
use Muon\ApiSchemaExport\Model\Webapi\TypeCollector;
use Muon\ApiSchemaExportAdminUi\Model\AclOperationFilter;
use PHPUnit\Framework\TestCase;

/**
 * @see \Muon\ApiSchemaExportAdminUi\Model\AclOperationFilter
 */
class AclOperationFilterTest extends TestCase
{
    /**
     * An operation whose resource the administrator holds survives.
     */
    public function testAllowedOperationsSurvive(): void
    {
        $filter = $this->makeFilter(['Magento_Catalog::products' => true]);

        $surface = $filter->filter($this->makeSurface([
            $this->makeOperation(['Magento_Catalog::products']),
        ]));

        self::assertCount(1, $surface->getOperations());
    }

    /**
     * An operation whose resource the administrator lacks is removed.
     *
     * An administrator who cannot call an endpoint should not receive a document describing it,
     * because that document is something they can forward to anyone.
     */
    public function testDisallowedOperationsAreRemoved(): void
    {
        $filter = $this->makeFilter([
            'Magento_Catalog::products' => true,
            'Magento_Sales::sales' => false,
        ]);

        $surface = $filter->filter($this->makeSurface([
            $this->makeOperation(['Magento_Catalog::products']),
            $this->makeOperation(['Magento_Sales::sales']),
        ]));

        self::assertCount(1, $surface->getOperations());
        self::assertSame(['Magento_Catalog::products'], $surface->getOperations()[0]->getAclResources());
    }

    /**
     * Every declared resource must be held, not just one of them.
     */
    public function testAllDeclaredResourcesAreRequired(): void
    {
        $filter = $this->makeFilter([
            'Magento_Catalog::products' => true,
            'Magento_Sales::sales' => false,
        ]);

        $surface = $filter->filter($this->makeSurface([
            $this->makeOperation(['Magento_Catalog::products', 'Magento_Sales::sales']),
            $this->makeOperation(['Magento_Catalog::products']),
        ]));

        self::assertCount(1, $surface->getOperations());
    }

    /**
     * An operation with no declared resources is anonymous REST and stays.
     */
    public function testAnonymousOperationsAlwaysSurvive(): void
    {
        $filter = $this->makeFilter([]);

        self::assertCount(1, $filter->filter($this->makeSurface([$this->makeOperation([])]))->getOperations());
    }

    /**
     * Filtering everything away must say so, not report an empty module.
     *
     * Falling through would make the export manager report "no REST routes are declared", which is
     * false and sends the administrator looking in the wrong place.
     */
    public function testFilteringEverythingAwayThrows(): void
    {
        $filter = $this->makeFilter(['Magento_Sales::sales' => false]);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('not allowed');

        $filter->filter($this->makeSurface([$this->makeOperation(['Magento_Sales::sales'])]));
    }

    /**
     * An already-empty surface passes through, so the export manager reports the real reason.
     */
    public function testAnAlreadyEmptySurfacePassesThrough(): void
    {
        $filter = $this->makeFilter([]);

        self::assertSame([], $filter->filter($this->makeSurface([]))->getOperations());
    }

    /**
     * Build a filter over a fixed permission table.
     *
     * @param array<string,bool> $permissions
     * @return \Muon\ApiSchemaExportAdminUi\Model\AclOperationFilter
     */
    private function makeFilter(array $permissions): AclOperationFilter
    {
        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturnCallback(
            static fn (?string $resource): bool => $resource !== null && ($permissions[$resource] ?? false)
        );

        $typeProcessor = $this->createStub(TypeProcessor::class);
        $typeProcessor->method('getTypesData')->willReturn([]);
        $typeProcessor->method('getArrayItemType')->willReturn('string');
        $typeProcessor->method('isTypeSimple')->willReturn(true);

        return new AclOperationFilter($authorization, new SurfaceSplitter(new TypeCollector($typeProcessor)));
    }

    /**
     * @param \Muon\ApiSchemaExport\Api\Data\OperationInterface[] $operations
     * @return \Muon\ApiSchemaExport\Api\Data\ApiSurfaceInterface
     */
    private function makeSurface(array $operations): ApiSurfaceInterface
    {
        return new ApiSurface($operations, ['Magento_Catalog'], []);
    }

    /**
     * @param string[] $aclResources
     * @return \Muon\ApiSchemaExport\Api\Data\OperationInterface
     */
    private function makeOperation(array $aclResources): OperationInterface
    {
        return new Operation(
            ['Magento_Catalog'],
            '/V1/products',
            'GET',
            'Magento\Catalog\Api\ProductRepositoryInterface',
            'getList',
            $aclResources,
            false,
            OperationInterface::KIND_SYNC,
            '',
            [],
            [],
            [],
            []
        );
    }
}
