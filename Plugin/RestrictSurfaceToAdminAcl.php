<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\ApiSchemaExportAdminUi\Plugin;

use Muon\ApiSchemaExport\Api\Data\ApiSurfaceInterface;
use Muon\ApiSchemaExport\Api\SurfaceResolverInterface;
use Muon\ApiSchemaExportAdminUi\Model\AclOperationFilter;

/**
 * Restricts a resolved surface to what the signed-in administrator may call.
 *
 * Registered in etc/adminhtml/di.xml, so it applies to admin requests only. The console command
 * runs in the global area and is deliberately left unfiltered — shell access already implies full
 * trust, and a CLI export that silently changed shape with the operator's role would be worse than
 * useless.
 *
 * Interception is the only place this can happen: the export manager resolves and renders in one
 * call, so a controller never sees the surface in between.
 */
class RestrictSurfaceToAdminAcl
{
    /**
     * @param \Muon\ApiSchemaExportAdminUi\Model\AclOperationFilter $aclOperationFilter
     */
    public function __construct(private readonly AclOperationFilter $aclOperationFilter)
    {
    }

    /**
     * Filter the resolved surface.
     *
     * @param \Muon\ApiSchemaExport\Api\SurfaceResolverInterface $subject
     * @param \Muon\ApiSchemaExport\Api\Data\ApiSurfaceInterface $result
     * @return \Muon\ApiSchemaExport\Api\Data\ApiSurfaceInterface
     * @throws \Magento\Framework\Exception\AuthorizationException When nothing survives the filter.
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterResolve(
        SurfaceResolverInterface $subject,
        ApiSurfaceInterface $result
    ): ApiSurfaceInterface {
        return $this->aclOperationFilter->filter($result);
    }
}
