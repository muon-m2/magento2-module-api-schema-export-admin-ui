<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\ApiSchemaExportAdminUi\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\InputException;
use Muon\ApiSchemaExport\Api\Data\ExportRequestInterface;
use Muon\ApiSchemaExport\Api\Data\RenderContextInterface;
use Muon\ApiSchemaExport\Api\RendererPoolInterface;
use Muon\ApiSchemaExport\Model\Data\ExportRequest;

/**
 * Builds an export request from admin form input.
 *
 * Every value from the request is validated here rather than in the controller, so the controller
 * stays an orchestrator and the validation has one home. Format codes are checked against the
 * renderer pool before use — a code from the request never reaches a class name.
 */
class ExportRequestBuilder
{
    /**
     * @param \Muon\ApiSchemaExport\Api\RendererPoolInterface $rendererPool
     */
    public function __construct(private readonly RendererPoolInterface $rendererPool)
    {
    }

    /**
     * Build the request.
     *
     * @param \Magento\Framework\App\RequestInterface $request
     * @return \Muon\ApiSchemaExport\Api\Data\ExportRequestInterface
     * @throws \Magento\Framework\Exception\InputException When selectors or formats are missing or unknown.
     */
    public function createFromRequest(RequestInterface $request): ExportRequestInterface
    {
        return new ExportRequest(
            $this->readSelectors($request),
            $this->readFormats($request),
            $request->getParam('yaml')
                ? RenderContextInterface::FORMAT_YAML
                : RenderContextInterface::FORMAT_JSON,
            $this->readOptionalString($request, 'filename'),
            $this->readOptionalString($request, 'base_url'),
            !$request->getParam('no_async'),
            (bool)$request->getParam('split')
        );
    }

    /**
     * Read and validate the comma-separated module selectors.
     *
     * @param \Magento\Framework\App\RequestInterface $request
     * @return string[]
     * @throws \Magento\Framework\Exception\InputException
     */
    private function readSelectors(RequestInterface $request): array
    {
        $raw = (string)$request->getParam('selectors', '');
        $selectors = array_values(array_filter(array_map('trim', explode(',', $raw))));

        if ($selectors === []) {
            throw new InputException(__('Enter at least one module selector.'));
        }

        return $selectors;
    }

    /**
     * Read and validate the requested formats.
     *
     * @param \Magento\Framework\App\RequestInterface $request
     * @return string[]
     * @throws \Magento\Framework\Exception\InputException
     */
    private function readFormats(RequestInterface $request): array
    {
        $available = $this->rendererPool->getCodes();
        $submitted = $request->getParam('formats');
        $formats = array_values(array_filter(array_map('strval', is_array($submitted) ? $submitted : [])));

        if ($formats === []) {
            throw new InputException(__('Select at least one output format.'));
        }

        $unknown = array_diff($formats, $available);
        if ($unknown !== []) {
            throw new InputException(
                __(
                    'Unknown format(s): %1. Available: %2.',
                    implode(', ', $unknown),
                    implode(', ', $available)
                )
            );
        }

        return $formats;
    }

    /**
     * Read an optional string parameter, treating blank as absent.
     *
     * @param \Magento\Framework\App\RequestInterface $request
     * @param string $name
     * @return string|null
     */
    private function readOptionalString(RequestInterface $request, string $name): ?string
    {
        $value = trim((string)$request->getParam($name, ''));

        return $value === '' ? null : $value;
    }
}
