<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\ApiSchemaExportAdminUi\Model;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Exception\AuthorizationException;
use Muon\ApiSchemaExport\Api\Data\ApiSurfaceInterface;
use Muon\ApiSchemaExport\Api\Data\OperationInterface;
use Muon\ApiSchemaExport\Model\Export\SurfaceSplitter;

/**
 * Removes operations the signed-in administrator is not allowed to call.
 *
 * The console command deliberately does not filter — shell access already implies full trust. The
 * admin screen does: an administrator who cannot call an endpoint should not be handed a document
 * describing it, because that document is something they can forward to anyone.
 *
 * This asymmetry is intentional, and it is the reason the filter lives in the admin module rather
 * than in the core one.
 */
class AclOperationFilter
{
    /**
     * @param \Magento\Framework\AuthorizationInterface $authorization
     * @param \Muon\ApiSchemaExport\Model\Export\SurfaceSplitter $surfaceSplitter
     */
    public function __construct(
        private readonly AuthorizationInterface $authorization,
        private readonly SurfaceSplitter $surfaceSplitter
    ) {
    }

    /**
     * Keep only the operations the current administrator may invoke.
     *
     * @param \Muon\ApiSchemaExport\Api\Data\ApiSurfaceInterface $surface
     * @return \Muon\ApiSchemaExport\Api\Data\ApiSurfaceInterface
     * @throws \Magento\Framework\Exception\AuthorizationException When nothing survives the filter.
     */
    public function filter(ApiSurfaceInterface $surface): ApiSurfaceInterface
    {
        $operations = $surface->getOperations();
        $allowed = array_values(
            array_filter($operations, fn (OperationInterface $operation): bool => $this->isAllowed($operation))
        );

        // Falling through with an empty surface would make the export manager report "no REST
        // routes are declared", which is false and sends the administrator looking in the wrong
        // place. The routes exist; this role cannot call them.
        if ($allowed === [] && $operations !== []) {
            throw new AuthorizationException(
                __('Your role is not allowed to call any endpoint in the selected modules.')
            );
        }

        return $this->surfaceSplitter->withOperations($surface, $allowed);
    }

    /**
     * Whether the current administrator holds every ACL resource an operation requires.
     *
     * An operation with no declared resources is anonymous REST, callable by anyone, so it is
     * always kept.
     *
     * @param \Muon\ApiSchemaExport\Api\Data\OperationInterface $operation
     * @return bool
     */
    private function isAllowed(OperationInterface $operation): bool
    {
        foreach ($operation->getAclResources() as $resource) {
            if (!$this->authorization->isAllowed($resource)) {
                return false;
            }
        }

        return true;
    }
}
