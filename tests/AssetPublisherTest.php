<?php

declare(strict_types=1);

namespace Yiisoft\Assets\Tests;

use Yiisoft\Assets\Exception\InvalidConfigException;
use Yiisoft\Assets\Tests\stubs\BaseAsset;
use Yiisoft\Assets\Tests\stubs\JqueryAsset;
use Yiisoft\Assets\Tests\stubs\SourceAsset;
use Yiisoft\Files\FileHelper;

/**
 * AssetPublisherTest.
 */
final class AssetPublisherTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->removeAssets('@basePath');
    }

    public function testBaseAppendtimestamp(): void
    {
        $bundle = new BaseAsset();

        $timestampCss = @filemtime($this->aliases->get($bundle->basePath) . '/' . $bundle->css[0]);
        $urlCss = "/baseUrl/css/basePath.css?v=$timestampCss";

        $timestampJs = @filemtime($this->aliases->get($bundle->basePath) . '/' . $bundle->js[0]);
        $urlJs = "/baseUrl/js/basePath.js?v=$timestampJs";

        $this->assertEmpty($this->assetManager->getAssetBundles());

        $this->publisher->setAppendTimestamp(true);

        $this->assetManager->register([BaseAsset::class]);

        $this->assertStringContainsString(
            $urlCss,
            $this->assetManager->getCssFiles()[$urlCss]['url']
        );
        $this->assertEquals(
            [
                'integrity' => 'integrity-hash',
                'crossorigin' => 'anonymous'
            ],
            $this->assetManager->getCssFiles()[$urlCss]['attributes']
        );

        $this->assertStringContainsString(
            $urlJs,
            $this->assetManager->getJsFiles()[$urlJs]['url']
        );
        $this->assertEquals(
            [
                'integrity' => 'integrity-hash',
                'crossorigin' => 'anonymous',
                'position' => 3
            ],
            $this->assetManager->getJsFiles()[$urlJs]['attributes']
        );
    }

    public function testPublisherSetAssetMap(): void
    {
        $urlJs = '//testme.css';

        $this->publisher->setAssetMap(
            [
                'jquery.js' => $urlJs,
            ]
        );

        $this->assertEmpty($this->assetManager->getAssetBundles());

        $this->assetManager->register([JqueryAsset::class]);

        $this->assertStringContainsString(
            $urlJs,
            $this->assetManager->getJsFiles()[$urlJs]['url']
        );
        $this->assertEquals(
            [
                'position' => 3
            ],
            $this->assetManager->getJsFiles()[$urlJs]['attributes']
        );
    }

    /**
     * @return array
     */
    public function registerFileDataProvider(): array
    {
        return [
            // Custom alias repeats in the asset URL
            [
                'css', '@web/assetSources/repeat/css/stub.css', false,
                '/repeat/assetSources/repeat/css/stub.css',
                '/repeat',
            ],
            [
                'js', '@web/assetSources/repeat/js/jquery.js', false,
                '/repeat/assetSources/repeat/js/jquery.js',
                '/repeat',
            ],
            // JS files registration
            [
                'js', '@web/assetSources/js/missing-file.js', true,
                '/baseUrl/assetSources/js/missing-file.js'
            ],
            [
                'js', '@web/assetSources/js/jquery.js', false,
                '/baseUrl/assetSources/js/jquery.js',
            ],
            [
                'js', 'http://example.com/assetSources/js/jquery.js', false,
                'http://example.com/assetSources/js/jquery.js',
            ],
            [
                'js', '//example.com/assetSources/js/jquery.js', false,
                '//example.com/assetSources/js/jquery.js',
            ],
            [
                'js', 'assetSources/js/jquery.js', false,
                'assetSources/js/jquery.js',
            ],
            [
                'js', '/assetSources/js/jquery.js', false,
                '/assetSources/js/jquery.js',
            ],
            // CSS file registration
            [
                'css', '@web/assetSources/css/missing-file.css', true,
                '/baseUrl/assetSources/css/missing-file.css',
            ],
            [
                'css', '@web/assetSources/css/stub.css', false,
                '/baseUrl/assetSources/css/stub.css',
            ],
            [
                'css', 'http://example.com/assetSources/css/stub.css', false,
                'http://example.com/assetSources/css/stub.css',
            ],
            [
                'css', '//example.com/assetSources/css/stub.css', false,
                '//example.com/assetSources/css/stub.css',
            ],
            [
                'css', 'assetSources/css/stub.css', false,
                'assetSources/css/stub.css',
            ],
            [
                'css', '/assetSources/css/stub.css', false,
                '/assetSources/css/stub.css',
            ],
            // Custom `@web` aliases
            [
                'js', '@web/assetSources/js/missing-file1.js', true,
                '/backend/assetSources/js/missing-file1.js',
                '/backend',
            ],
            [
                'js', 'http://full-url.example.com/backend/assetSources/js/missing-file.js', true,
                'http://full-url.example.com/backend/assetSources/js/missing-file.js',
                '/backend',
            ],
            [
                'css', '//backend/backend/assetSources/js/missing-file.js', true,
                '//backend/backend/assetSources/js/missing-file.js',
                '/backend',
            ],
            [
                'css', '@web/assetSources/css/stub.css', false,
                '/en/blog/backend/assetSources/css/stub.css',
                '/en/blog/backend',
            ],
            // UTF-8 chars
            [
                'css', '@web/assetSources/css/stub.css', false,
                '/рус/сайт/assetSources/css/stub.css',
                '/рус/сайт',
            ],
            [
                'js', '@web/assetSources/js/jquery.js', false,
                '/汉语/漢語/assetSources/js/jquery.js',
                '/汉语/漢語',
            ],
        ];
    }

    /**
     * @dataProvider registerFileDataProvider
     *
     * @param string $type either `js` or `css`
     * @param string $path
     * @param bool $appendTimestamp
     * @param string $expected
     * @param string|null $webAlias
     */
    public function testRegisterFileAppendTimestamp(
        string $type,
        string $path,
        bool $appendTimestamp,
        string $expected,
        ?string $webAlias = null
    ): void {
        $originalAlias = $this->aliases->get('@web');

        if ($webAlias === null) {
            $webAlias = $originalAlias;
        }

        $this->aliases->set('@web', $webAlias);

        $path = $this->aliases->get($path);

        $this->publisher->setAppendTimestamp($appendTimestamp);

        $method = 'register' . ucfirst($type) . 'File';

        $this->assetManager->$method($path, [], null);

        $this->assertStringContainsString(
            $expected,
            $type === 'css' ? $this->assetManager->getCssFiles()[$expected]['url']
                : $this->assetManager->getJsFiles()[$expected]['url']
        );
    }

    public function testSourcesCssJsDefaultOptions(): void
    {
        $bundle = new SourceAsset();

        $sourcePath = $this->aliases->get($bundle->sourcePath);
        $path = (is_file($sourcePath) ? \dirname($sourcePath) : $sourcePath) . @filemtime($sourcePath);
        $hash = sprintf('%x', crc32($path . '|' . $this->publisher->getLinkAssets()));

        $this->publisher->setCssDefaultOptions([
            'media' => 'none'
        ]);

        $this->publisher->setJsDefaultOptions([
            'position' => 2
        ]);

        $this->assertEmpty($this->assetManager->getAssetBundles());

        $this->assetManager->register([SourceAsset::class]);

        $this->assertEquals(
            [
                'media' => 'none'
            ],
            $this->assetManager->getCssFiles()["/baseUrl/$hash/css/stub.css"]['attributes']
        );
        $this->assertEquals(
            [
                'position' => 2
            ],
            $this->assetManager->getJsFiles()['/js/jquery.js']['attributes']
        );
        $this->assertEquals(
            [
                'position' => 2
            ],
            $this->assetManager->getJsFiles()["/baseUrl/$hash/js/stub.js"]['attributes']
        );
    }

    public function testSourceSetHashCallback(): void
    {
        $this->publisher->setHashCallback(function () {
            return 'HashCallback';
        });

        $this->assertEmpty($this->assetManager->getAssetBundles());

        $this->assetManager->register([SourceAsset::class]);

        $this->assertStringContainsString(
            '/baseUrl/HashCallback/css/stub.css',
            $this->assetManager->getCssFiles()['/baseUrl/HashCallback/css/stub.css']['url']
        );
        $this->assertEquals(
            [],
            $this->assetManager->getCssFiles()['/baseUrl/HashCallback/css/stub.css']['attributes']
        );

        $this->assertStringContainsString(
            '/js/jquery.js',
            $this->assetManager->getJsFiles()['/js/jquery.js']['url']
        );
        $this->assertEquals(
            [
                'position' => 3
            ],
            $this->assetManager->getJsFiles()['/js/jquery.js']['attributes']
        );

        $this->assertStringContainsString(
            '/baseUrl/HashCallback/js/stub.js',
            $this->assetManager->getJsFiles()['/baseUrl/HashCallback/js/stub.js']['url']
        );
        $this->assertEquals(
            [
                'position' => 3
            ],
            $this->assetManager->getJsFiles()['/baseUrl/HashCallback/js/stub.js']['attributes']
        );
    }

    public function testSourcesPublishOptionsOnlyRegex(): void
    {
        $bundle = new SourceAsset();

        $bundle->publishOptions = [
            'only' => [
                'js/*'
            ],
        ];

        [$bundle->basePath, $bundle->baseUrl] = $this->publisher->publish($bundle);

        $notNeededFilesDir = dirname($bundle->basePath . DIRECTORY_SEPARATOR . $bundle->css[0]);

        $this->assertFileDoesNotExist($notNeededFilesDir);

        foreach ($bundle->js as $filename) {
            $publishedFile = $bundle->basePath . DIRECTORY_SEPARATOR . $filename;

            $this->assertFileExists($publishedFile);
        }

        $this->assertDirectoryExists(dirname($bundle->basePath . DIRECTORY_SEPARATOR . $bundle->js[0]));
        $this->assertDirectoryExists($bundle->basePath);
    }

    public function testSourcesPathException(): void
    {
        $bundle = new SourceAsset();

        $bundle->sourcePath = '/wrong';

        $message = "The sourcePath to be published does not exist: $bundle->sourcePath";

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage($message);

        $this->publisher->publish($bundle);
    }

    public function testSourcesPublishedBySymlinkIssue9333(): void
    {
        $this->publisher->setLinkAssets(true);

        $this->publisher->setHashCallback(
            static function ($path) {
                return sprintf('%x/%x', crc32($path), crc32('3.0-dev'));
            }
        );

        $bundle = $this->verifySourcesPublishedBySymlink();

        $this->assertDirectoryExists(dirname($bundle->basePath));
    }

    private function verifySourcesPublishedBySymlink(): SourceAsset
    {
        $bundle = new SourceAsset();

        [$bundle->basePath, $bundle->baseUrl] = $this->publisher->publish($bundle);

        $this->assertDirectoryExists($bundle->basePath);

        foreach ($bundle->js as $filename) {
            $publishedFile = $bundle->basePath . DIRECTORY_SEPARATOR . $filename;
            $sourceFile = $bundle->basePath . DIRECTORY_SEPARATOR . $filename;

            $this->assertTrue(is_link($bundle->basePath));
            $this->assertFileExists($publishedFile);
            $this->assertFileEquals($publishedFile, $sourceFile);
        }

        $this->assertTrue(FileHelper::unlink($bundle->basePath));

        return $bundle;
    }
}
