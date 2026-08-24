<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\ApiSchemaExportAdminUi\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Muon\ApiSchemaExport\Model\FilenameSanitizer;
use ZipArchive;

/**
 * Packs several generated files into one archive for download.
 *
 * The archive is written under var/tmp and streamed with rm => true, so it exists only for the
 * duration of the response. Nothing accumulates on disk.
 */
class ZipPackager
{
    /**
     * Directory the archive is staged in, relative to var.
     */
    private const STAGING_DIRECTORY = 'tmp';

    /**
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Muon\ApiSchemaExport\Model\FilenameSanitizer $filenameSanitizer
     */
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly FilenameSanitizer $filenameSanitizer
    ) {
    }

    /**
     * Resolve a safe archive name from user input.
     *
     * Sanitising lives here rather than in the caller, so no path can reach the archive writer
     * unchecked no matter which front-end asks for it.
     *
     * @param string|null $baseFilename
     * @return string
     */
    public function resolveArchiveName(?string $baseFilename): string
    {
        return $this->filenameSanitizer->sanitizeBase($baseFilename ?? 'api-schema');
    }

    /**
     * Write the files into an archive and return its path relative to var.
     *
     * @param array<string,string> $files Filename to contents.
     * @param string $archiveName Base name, without extension. Sanitised again here regardless.
     * @return string Path relative to the var directory, suitable for FileFactory.
     * @throws \Magento\Framework\Exception\LocalizedException When the archive cannot be created.
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function pack(array $files, string $archiveName): string
    {
        // Sanitised again here rather than trusting the caller: this method is public, so a
        // future caller could pass unfiltered input straight through and write outside var/tmp.
        $safeName = $this->filenameSanitizer->sanitizeBase($archiveName);

        $varDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $varDirectory->create(self::STAGING_DIRECTORY);

        $relativePath = self::STAGING_DIRECTORY . '/' . $safeName . '.zip';
        $absolutePath = $varDirectory->getAbsolutePath($relativePath);

        $archive = new ZipArchive();
        $opened = $archive->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($opened !== true) {
            throw new LocalizedException(
                __('Could not create the archive (code %1).', (string)$opened)
            );
        }

        foreach ($files as $filename => $contents) {
            // A failed entry would otherwise stream a ZIP that is silently missing a file.
            if (!$archive->addFromString((string)$filename, $contents)) {
                $archive->close();
                throw new LocalizedException(
                    __('Could not add "%1" to the archive.', (string)$filename)
                );
            }
        }

        if (!$archive->close()) {
            throw new LocalizedException(__('Could not finalise the archive.'));
        }

        return $relativePath;
    }
}
