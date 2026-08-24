<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\ApiSchemaExportAdminUi\Block\Adminhtml\Export;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
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
     * Key the failed submission is stashed under.
     */
    public const FORM_DATA_KEY = 'muon_apischemaexport_form';

    /**
     * Format ticked when there is nothing to restore.
     */
    private const DEFAULT_FORMAT = 'openapi';

    /**
     * @var array<string,mixed>|null
     */
    private ?array $formData = null;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Muon\ApiSchemaExport\Api\RendererPoolInterface $rendererPool
     * @param \Magento\Framework\Module\ModuleListInterface $moduleList
     * @param \Magento\Framework\App\Request\DataPersistorInterface $dataPersistor
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly RendererPoolInterface $rendererPool,
        private readonly ModuleListInterface $moduleList,
        private readonly DataPersistorInterface $dataPersistor,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get the values the last failed submission carried, so the form can be re-filled.
     *
     * Read once and cleared: leaving it behind would re-populate the form on a later, unrelated
     * visit with input the user has moved on from.
     *
     * @return array<string,mixed>
     */
    public function getFormData(): array
    {
        $data = $this->dataPersistor->get(self::FORM_DATA_KEY);
        if ($data === null) {
            return [];
        }

        $this->dataPersistor->clear(self::FORM_DATA_KEY);

        return is_array($data) ? $data : [];
    }

    /**
     * Get one persisted field, or an empty string.
     *
     * @param string $field
     * @return string
     */
    public function getFieldValue(string $field): string
    {
        $value = $this->getFormDataOnce()[$field] ?? '';

        return is_scalar($value) ? (string)$value : '';
    }

    /**
     * Whether a persisted checkbox was ticked.
     *
     * @param string $field
     * @return bool
     */
    public function isFieldChecked(string $field): bool
    {
        return !empty($this->getFormDataOnce()[$field]);
    }

    /**
     * Whether a format was selected on the last failed submission.
     *
     * @param string $code
     * @return bool
     */
    public function isFormatSelected(string $code): bool
    {
        $data = $this->getFormDataOnce();
        if (!isset($data['formats']) || !is_array($data['formats'])) {
            // Nothing to restore, so fall back to the default selection.
            return $code === self::DEFAULT_FORMAT;
        }

        return in_array($code, $data['formats'], true);
    }

    /**
     * Read the persisted data once per render.
     *
     * @return array<string,mixed>
     */
    private function getFormDataOnce(): array
    {
        if ($this->formData === null) {
            $this->formData = $this->getFormData();
        }

        return $this->formData;
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
