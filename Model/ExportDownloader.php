<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\ApiSchemaExportAdminUi\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Muon\ApiSchemaExport\Api\Data\ExportResultInterface;

/**
 * Turns an export result into a download response.
 *
 * One file downloads as itself; several download as a ZIP. Either way the staged copy is removed
 * once the response has been sent.
 */
class ExportDownloader
{
    /**
     * Content types keyed by the filename suffix a renderer produces.
     */
    private const CONTENT_TYPES = [
        '.json' => 'application/json',
        '.yaml' => 'application/yaml',
        '.http' => 'text/plain',
    ];

    /**
     * Used when no known suffix matches.
     */
    private const DEFAULT_CONTENT_TYPE = 'text/plain';

    /**
     * @param \Magento\Framework\App\Response\Http\FileFactory $fileFactory
     * @param \Muon\ApiSchemaExportAdminUi\Model\ZipPackager $zipPackager
     */
    public function __construct(
        private readonly FileFactory $fileFactory,
        private readonly ZipPackager $zipPackager
    ) {
    }

    /**
     * Build the download response for a result.
     *
     * @param \Muon\ApiSchemaExport\Api\Data\ExportResultInterface $result
     * @return \Magento\Framework\App\ResponseInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function download(ExportResultInterface $result): ResponseInterface
    {
        return $result->getFileCount() === 1
            ? $this->single($result)
            : $this->archive($result);
    }

    /**
     * Stream a single generated file.
     *
     * @param \Muon\ApiSchemaExport\Api\Data\ExportResultInterface $result
     * @return \Magento\Framework\App\ResponseInterface
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    private function single(ExportResultInterface $result): ResponseInterface
    {
        $files = $result->getFiles();
        $filename = (string)array_key_first($files);

        // FileFactory writes whatever it is given into $baseDir, and only deletes it afterwards
        // when the content is the array form carrying rm. Passing a bare string would therefore
        // leave one file behind in var/ for every single download, permanently.
        return $this->fileFactory->create(
            $filename,
            ['type' => 'string', 'value' => $files[$filename], 'rm' => true],
            DirectoryList::VAR_DIR,
            $this->resolveContentType($filename)
        );
    }

    /**
     * Pack several files and stream the archive, removing it afterwards.
     *
     * @param \Muon\ApiSchemaExport\Api\Data\ExportResultInterface $result
     * @return \Magento\Framework\App\ResponseInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    private function archive(ExportResultInterface $result): ResponseInterface
    {
        // The same base name the individual files carry, so a multi-format download is not
        // named differently from a single-format one.
        $archiveName = $this->zipPackager->resolveArchiveName($result->getBaseName());
        $relativePath = $this->zipPackager->pack($result->getFiles(), $archiveName);

        return $this->fileFactory->create(
            $archiveName . '.zip',
            ['type' => 'filename', 'value' => $relativePath, 'rm' => true],
            DirectoryList::VAR_DIR,
            'application/zip'
        );
    }

    /**
     * Resolve a content type from a generated file's suffix.
     *
     * @param string $filename
     * @return string
     */
    private function resolveContentType(string $filename): string
    {
        foreach (self::CONTENT_TYPES as $suffix => $contentType) {
            if (str_ends_with($filename, $suffix)) {
                return $contentType;
            }
        }

        return self::DEFAULT_CONTENT_TYPE;
    }
}
