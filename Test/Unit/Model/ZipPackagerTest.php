<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\ApiSchemaExportAdminUi\Test\Unit\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Muon\ApiSchemaExport\Model\FilenameSanitizer;
use Muon\ApiSchemaExportAdminUi\Model\ZipPackager;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * @see \Muon\ApiSchemaExportAdminUi\Model\ZipPackager
 */
class ZipPackagerTest extends TestCase
{
    /**
     * @var string
     */
    private string $stagingDirectory;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->stagingDirectory = sys_get_temp_dir() . '/muon-zip-' . bin2hex(random_bytes(6));
        mkdir($this->stagingDirectory . '/tmp', 0777, true);
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        foreach (glob($this->stagingDirectory . '/tmp/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->stagingDirectory . '/tmp')) {
            rmdir($this->stagingDirectory . '/tmp');
        }
        if (is_dir($this->stagingDirectory)) {
            rmdir($this->stagingDirectory);
        }
    }

    /**
     * Every supplied file appears in the archive under its own name.
     */
    public function testEveryFileIsPackedUnderItsOwnName(): void
    {
        $packager = $this->makePackager();

        $relativePath = $packager->pack(
            [
                'muon.openapi.json' => '{"openapi":"3.1.0"}',
                'muon.postman_collection.json' => '{"info":{}}',
            ],
            'muon-api'
        );

        self::assertSame('tmp/muon-api.zip', $relativePath);

        $archive = new ZipArchive();
        self::assertTrue($archive->open($this->stagingDirectory . '/' . $relativePath));
        self::assertSame(2, $archive->numFiles);
        self::assertSame('{"openapi":"3.1.0"}', $archive->getFromName('muon.openapi.json'));
        self::assertSame('{"info":{}}', $archive->getFromName('muon.postman_collection.json'));
        $archive->close();
    }

    /**
     * The archive is staged under var/tmp, which the download then removes.
     */
    public function testArchiveIsStagedUnderTmp(): void
    {
        $packager = $this->makePackager();

        self::assertStringStartsWith('tmp/', $packager->pack(['a.json' => '{}'], 'x'));
    }

    /**
     * Archive naming goes through the sanitiser, so no path can reach the writer.
     */
    public function testArchiveNameIsSanitised(): void
    {
        $packager = $this->makePackager();

        self::assertSame('etc-passwd', $packager->resolveArchiveName('../../etc/passwd'));
        self::assertSame('api-schema', $packager->resolveArchiveName(null));
        self::assertSame('my-export', $packager->resolveArchiveName('my-export'));
    }

    /**
     * An unopenable target is reported as a localized error, not a silent empty download.
     *
     * The target here is an existing directory, which ZipArchive::open() rejects with an error
     * code rather than deferring to close().
     */
    public function testUnopenableArchiveThrows(): void
    {
        $packager = $this->makePackager($this->stagingDirectory);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Could not create the archive');

        $packager->pack(['a.json' => '{}'], 'x');
    }

    /**
     * Build a packager writing into the temporary staging directory.
     *
     * @param string|null $absoluteOverride
     * @return \Muon\ApiSchemaExportAdminUi\Model\ZipPackager
     */
    private function makePackager(?string $absoluteOverride = null): ZipPackager
    {
        $base = $this->stagingDirectory;

        $directory = $this->createStub(WriteInterface::class);
        $directory->method('create')->willReturn(true);
        $directory->method('getAbsolutePath')->willReturnCallback(
            static fn (string $path): string => $absoluteOverride ?? $base . '/' . $path
        );

        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->willReturnCallback(
            static fn (string $code): WriteInterface => $code === DirectoryList::VAR_DIR
                ? $directory
                : $directory
        );

        return new ZipPackager($filesystem, new FilenameSanitizer());
    }
}
