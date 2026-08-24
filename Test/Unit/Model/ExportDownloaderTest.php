<?php
/**
 * Copyright © Muon. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Muon\ApiSchemaExportAdminUi\Test\Unit\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Muon\ApiSchemaExport\Api\Data\ExportResultInterface;
use Muon\ApiSchemaExportAdminUi\Model\ExportDownloader;
use Muon\ApiSchemaExportAdminUi\Model\ZipPackager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see \Muon\ApiSchemaExportAdminUi\Model\ExportDownloader
 */
class ExportDownloaderTest extends TestCase
{
    /**
     * @var MockObject&FileFactory
     */
    /**
     * @var MockObject
     */
    private MockObject $fileFactory;

    /**
     * @var MockObject&ZipPackager
     */
    /**
     * @var MockObject
     */
    private MockObject $zipPackager;

    /**
     * @var ExportDownloader
     */
    private ExportDownloader $downloader;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->fileFactory = $this->createMock(FileFactory::class);
        $this->zipPackager = $this->createMock(ZipPackager::class);
        $this->downloader = new ExportDownloader($this->fileFactory, $this->zipPackager);
    }

    /**
     * A single file is handed to FileFactory in the removable string form.
     *
     * This is the regression guard for R5-001. FileFactory writes whatever it is given into the
     * base directory and only deletes it afterwards when the content is the array form carrying
     * rm. Passing a bare string leaves one file behind in var/ for every download, permanently.
     */
    public function testSingleFileIsPassedInTheRemovableStringForm(): void
    {
        $this->zipPackager->expects(self::never())->method('pack');
        $this->fileFactory->expects(self::once())
            ->method('create')
            ->with(
                'muon.openapi.json',
                ['type' => 'string', 'value' => '{"openapi":"3.1.0"}', 'rm' => true],
                DirectoryList::VAR_DIR,
                'application/json'
            )
            ->willReturn($this->createStub(ResponseInterface::class));

        $this->downloader->download($this->makeResult(['muon.openapi.json' => '{"openapi":"3.1.0"}']));
    }

    /**
     * Content type follows the generated file's suffix.
     *
     * @param string $filename
     * @param string $expected
     * @dataProvider contentTypeProvider
     */
    #[DataProvider('contentTypeProvider')]
    public function testContentTypeFollowsTheSuffix(string $filename, string $expected): void
    {
        $this->zipPackager->expects(self::never())->method('pack');
        $this->fileFactory->expects(self::once())
            ->method('create')
            ->with($filename, self::anything(), DirectoryList::VAR_DIR, $expected)
            ->willReturn($this->createStub(ResponseInterface::class));

        $this->downloader->download($this->makeResult([$filename => 'x']));
    }

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function contentTypeProvider(): array
    {
        return [
            'openapi json' => ['muon.openapi.json', 'application/json'],
            'postman collection' => ['muon.postman_collection.json', 'application/json'],
            'openapi yaml' => ['muon.openapi.yaml', 'application/yaml'],
            'http client file' => ['muon.http', 'text/plain'],
            'unknown suffix' => ['muon.bin', 'text/plain'],
        ];
    }

    /**
     * Several files are packed and streamed as a removable archive.
     */
    public function testSeveralFilesAreStreamedAsARemovableArchive(): void
    {
        $files = ['a.json' => '{}', 'b.http' => 'GET /'];

        $this->zipPackager->expects(self::once())->method('resolveArchiveName')->willReturn('muon-api');
        $this->zipPackager->expects(self::once())
            ->method('pack')
            ->with($files, 'muon-api')
            ->willReturn('tmp/muon-api.zip');

        $this->fileFactory->expects(self::once())
            ->method('create')
            ->with(
                'muon-api.zip',
                ['type' => 'filename', 'value' => 'tmp/muon-api.zip', 'rm' => true],
                DirectoryList::VAR_DIR,
                'application/zip'
            )
            ->willReturn($this->createStub(ResponseInterface::class));

        $this->downloader->download($this->makeResult($files, 'muon-api'));
    }

    /**
     * Archive naming is delegated, so the sanitiser cannot be bypassed here.
     */
    public function testArchiveNamingIsDelegatedToThePackager(): void
    {
        $this->zipPackager->expects(self::once())
            ->method('resolveArchiveName')
            ->with('../../etc/passwd')
            ->willReturn('etc-passwd');
        $this->zipPackager->method('pack')->willReturn('tmp/etc-passwd.zip');
        $this->fileFactory->expects(self::once())
            ->method('create')
            ->with('etc-passwd.zip', self::anything(), self::anything(), self::anything())
            ->willReturn($this->createStub(ResponseInterface::class));

        $this->downloader->download($this->makeResult(['a.json' => '{}', 'b.json' => '{}'], '../../etc/passwd'));
    }

    /**
     * @param array<string,string> $files
     * @param string $baseName
     * @return \Muon\ApiSchemaExport\Api\Data\ExportResultInterface
     */
    private function makeResult(array $files, string $baseName = 'muon'): ExportResultInterface
    {
        $result = $this->createStub(ExportResultInterface::class);
        $result->method('getFiles')->willReturn($files);
        $result->method('getFileCount')->willReturn(count($files));
        $result->method('getBaseName')->willReturn($baseName);

        return $result;
    }
}
