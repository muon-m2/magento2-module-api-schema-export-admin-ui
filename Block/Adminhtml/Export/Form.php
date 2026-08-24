<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\ApiSchemaExportAdminUi\Block\Adminhtml\Export;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Module\ModuleListInterface;
use Muon\ApiSchemaExport\Api\RendererPoolInterface;

/**
 * Supplies the export form with its choices.
 *
 * A plain template block rather than a ui_component form: there is no entity here for a
 * DataProvider to bind to, and the screen is a stateless action, not an edit page.
 */
class Form extends Template
{
    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Muon\ApiSchemaExport\Api\RendererPoolInterface $rendererPool
     * @param \Magento\Framework\Module\ModuleListInterface $moduleList
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly RendererPoolInterface $rendererPool,
        private readonly ModuleListInterface $moduleList,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get the URL the form posts to.
     *
     * @return string
     */
    public function getFormActionUrl(): string
    {
        return $this->getUrl('muon_apischemaexport/export/generate');
    }

    /**
     * Get the available output formats as code to label pairs.
     *
     * @return array<string,string>
     */
    public function getFormats(): array
    {
        $formats = [];
        foreach ($this->rendererPool->getAll() as $code => $renderer) {
            $formats[(string)$code] = (string)$renderer->getLabel();
        }

        return $formats;
    }

    /**
     * Get every enabled module name, for the selector's suggestion list.
     *
     * @return string[]
     */
    public function getModuleNames(): array
    {
        $names = $this->moduleList->getNames();
        sort($names);

        return $names;
    }
}
