<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\ApiSchemaExportAdminUi\Controller\Adminhtml\Export;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Muon\ApiSchemaExport\Api\ExportManagerInterface;
use Muon\ApiSchemaExportAdminUi\Model\ExportDownloader;
use Muon\ApiSchemaExportAdminUi\Block\Adminhtml\Export\Form;
use Muon\ApiSchemaExportAdminUi\Model\ExportRequestBuilder;
use Psr\Log\LoggerInterface;

/**
 * Generates the requested files and streams them back.
 *
 * POST only, so Magento's admin form-key validation applies — this controller does not opt out of
 * CSRF protection. Building the request, rendering it and delivering it each live elsewhere; this
 * class sequences them and turns failures into admin messages.
 */
class Generate extends Action implements HttpPostActionInterface
{
    /**
     * @inheritDoc
     */
    public const ADMIN_RESOURCE = 'Muon_ApiSchemaExport::export';

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Muon\ApiSchemaExport\Api\ExportManagerInterface $exportManager
     * @param \Muon\ApiSchemaExportAdminUi\Model\ExportRequestBuilder $exportRequestBuilder
     * @param \Muon\ApiSchemaExportAdminUi\Model\ExportDownloader $exportDownloader
     * @param \Magento\Framework\App\Request\DataPersistorInterface $dataPersistor
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        Action\Context $context,
        private readonly ExportManagerInterface $exportManager,
        private readonly ExportRequestBuilder $exportRequestBuilder,
        private readonly ExportDownloader $exportDownloader,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * Generate and stream the export.
     *
     * @return \Magento\Framework\Controller\ResultInterface|\Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
        try {
            $request = $this->exportRequestBuilder->createFromRequest($this->getRequest());
            $result = $this->exportManager->export($request);

            return $this->exportDownloader->download($result);
        } catch (LocalizedException $exception) {
            $this->persistSubmission();
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Exception $exception) {
            $this->persistSubmission();
            // An unexpected exception's message can carry filesystem paths or internals, so it is
            // logged for an operator rather than shown in the browser.
            $this->logger->error(
                'Muon_ApiSchemaExport: admin export failed.',
                ['exception' => $exception]
            );
            $this->messageManager->addErrorMessage(
                __('Could not generate the export. See the system log for details.')
            );
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }

    /**
     * Stash the submitted values so the form comes back filled in rather than blank.
     *
     * Re-typing a module selector and re-ticking four formats after a validation error is the
     * difference between a usable screen and a hostile one. The form key is dropped: it is
     * regenerated per page and stashing it would be pointless at best.
     *
     * @return void
     */
    private function persistSubmission(): void
    {
        $params = $this->getRequest()->getParams();
        unset($params['form_key'], $params['key']);

        $this->dataPersistor->set(Form::FORM_DATA_KEY, $params);
    }
}
