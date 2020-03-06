<?php

declare(strict_types=1);

namespace Buddy\Repman\Tests\Unit\Service\Organization;

use Buddy\Repman\Query\User\Model\PackageName;
use Buddy\Repman\Service\Dist;
use Buddy\Repman\Service\Dist\Storage;
use Buddy\Repman\Service\Dist\Storage\FileStorage;
use Buddy\Repman\Service\Dist\Storage\InMemoryStorage;
use Buddy\Repman\Service\Organization\PackageManager;
use Buddy\Repman\Tests\Doubles\FakeDownloader;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;

final class PackageManagerTest extends TestCase
{
    private PackageManager $manager;
    private string $baseDir;

    protected function setUp(): void
    {
        $this->manager = new PackageManager(new InMemoryStorage(), __DIR__.'/../../../Resources');
        $this->baseDir = sys_get_temp_dir().'/repman';
    }

    public function testFindProvidersForPackage(): void
    {
        $providers = $this->manager->findProviders('buddy', [
            new PackageName('id', 'buddy-works/repman'),
            new PackageName('id', 'not-exist/missing'),
        ]);

        self::assertEquals(['buddy-works/repman' => ['1.2.3' => [
            'version' => '1.2.3',
            'dist' => [
                'type' => 'zip',
                'url' => '/path/to/reference.zip',
                'reference' => 'ac7dcaf888af2324cd14200769362129c8dd8550',
            ],
            'version_normalized' => '1.2.3.0',
        ]]], $providers);
    }

    public function testReturnDistributionFilenameWhenExist(): void
    {
        /** @phpstan-var mixed $storage */
        $storage = $this->prophesize(Storage::class);
        $storage->has(Argument::type(Dist::class))->willReturn(true);
        $storage->download(Argument::cetera())->shouldNotBeCalled();
        $storage->filename(Argument::type(Dist::class))->willReturn(
            __DIR__.'/../../../Resources/buddy/dist/buddy-works/repman/1.2.3.0_ac7dcaf888af2324cd14200769362129c8dd8550.zip'
        );

        $manager = new PackageManager($storage->reveal(), __DIR__.'/../../../Resources');

        self::assertStringContainsString(
            '1.2.3.0_ac7dcaf888af2324cd14200769362129c8dd8550.zip',
            $manager->distFilename('buddy', 'buddy-works/repman', '1.2.3.0', 'ac7dcaf888af2324cd14200769362129c8dd8550', 'zip')->get()
        );
    }

    public function testRemoveProvider(): void
    {
        $manager = new PackageManager(
            new FileStorage($this->baseDir, new FakeDownloader()),
            $this->baseDir
        );

        $org = 'buddy';
        $package = 'hello/world';

        $manager->saveProvider([], $org, $package);
        $manager->removeProvider($org, $package);

        self::assertTrue(is_dir($this->baseDir.'/buddy'));
        self::assertFalse(is_dir($this->baseDir.'/buddy/p/hello'));
    }

    public function testRemoveOrganizationDir(): void
    {
        $manager = new PackageManager(
            new FileStorage($this->baseDir, new FakeDownloader()),
            $this->baseDir
        );

        $org = 'buddy';
        $package = 'hello/world';

        $manager->saveProvider([], $org, $package);
        $manager->removeProvider($org, $package)
            ->removeOrganizationDir($org);

        self::assertFalse(is_dir($this->baseDir.'/buddy'));
    }
}
