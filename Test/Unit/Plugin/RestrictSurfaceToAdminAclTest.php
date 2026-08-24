<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\ApiSchemaExportAdminUi\Test\Unit\Plugin;

use Muon\ApiSchemaExport\Api\Data\ApiSurfaceInterface;
use Muon\ApiSchemaExport\Api\SurfaceResolverInterface;
use Muon\ApiSchemaExportAdminUi\Model\AclOperationFilter;
use Muon\ApiSchemaExportAdminUi\Plugin\RestrictSurfaceToAdminAcl;
use PHPUnit\Framework\TestCase;

/**
 * @see \Muon\ApiSchemaExportAdminUi\Plugin\RestrictSurfaceToAdminAcl
 */
class RestrictSurfaceToAdminAclTest extends TestCase
{
    /**
     * The resolved surface is passed through the ACL filter and the filtered one returned.
     *
     * Interception is the only place this can happen: the export manager resolves and renders in
     * one call, so a controller never sees the surface in between.
     */
    public function testTheResolvedSurfaceIsFiltered(): void
    {
        $resolved = $this->createStub(ApiSurfaceInterface::class);
        $filtered = $this->createStub(ApiSurfaceInterface::class);

        $aclFilter = $this->createMock(AclOperationFilter::class);
        $aclFilter->expects(self::once())->method('filter')->with($resolved)->willReturn($filtered);

        $plugin = new RestrictSurfaceToAdminAcl($aclFilter);

        self::assertSame(
            $filtered,
            $plugin->afterResolve($this->createStub(SurfaceResolverInterface::class), $resolved)
        );
    }
}
