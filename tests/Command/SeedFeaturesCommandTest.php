<?php

namespace App\Tests\Command;

use App\Command\SeedFeaturesCommand;
use App\Entity\Feature;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class SeedFeaturesCommandTest extends TestCase
{
    public function testExecuteSeedsFeaturesSuccessfully(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(Feature::class)->willReturn($repo);
        $em->expects($this->atLeastOnce())->method('persist');
        $em->expects($this->once())->method('flush');

        $command = new SeedFeaturesCommand($em);
        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find('app:seed-features'));
        $statusCode = $commandTester->execute([]);

        $this->assertEquals(0, $statusCode);
        $this->assertStringContainsString('Approvisionnement terminé avec succès', $commandTester->getDisplay());
    }

    public function testExecutePurgesWhenPurgeOptionProvided(): void
    {
        $existingFeature = new Feature();
        $existingFeature->setCode('OLD_FEATURE');

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findAll')->willReturn([$existingFeature]);
        $repo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(Feature::class)->willReturn($repo);
        $em->expects($this->once())->method('remove')->with($existingFeature);
        $em->expects($this->atLeastOnce())->method('flush');

        $command = new SeedFeaturesCommand($em);
        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find('app:seed-features'));
        $statusCode = $commandTester->execute(['--purge' => true]);

        $this->assertEquals(0, $statusCode);
        $this->assertStringContainsString('Toutes les fonctionnalités existantes ont été purgées.', $commandTester->getDisplay());
    }
}
