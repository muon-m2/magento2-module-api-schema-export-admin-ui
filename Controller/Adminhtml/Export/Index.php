<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\ApiSchemaExportAdminUi\Controller\Adminhtml\Export;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * Renders the export form.
 */
class Index extends Action implements HttpGetActionInterface
{
    /**
     * @inheritDoc
     */
    public const ADMIN_RESOURCE = 'Muon_ApiSchemaExport::export';

    /**
     * @param \Magento\Backend\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(
        Action\Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Render the page.
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute(): ResultInterface
    {
        // Must be the framework factory, not Magento\Backend\Model\View\Result\PageFactory.
        // Only the framework one calls $page->addDefaultHandle(); the auto-generated Backend
        // factory is a plain create(), so the `default` layout handle is never added, the `menu`
        // block that handle declares never exists, and setActiveMenu() fatals on false. The
        // adminhtml preference still makes this a Backend page, hence the cast below.
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Muon_ApiSchemaExportAdminUi::export');
        $resultPage->getConfig()->getTitle()->prepend((string)__('API Schema Export'));

        return $resultPage;
    }
}
