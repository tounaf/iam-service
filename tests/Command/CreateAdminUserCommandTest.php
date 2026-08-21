<?php

namespace App\Tests\Command;

use App\Command\CreateAdminUserCommand;
use App\Entity\Fiangonana;
use App\Entity\Membre;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CreateAdminUserCommandTest extends TestCase
{
    public function testCreateAdminUserCommandCreatesNewAdmin(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);

        $membreRepo = $this->createMock(EntityRepository::class);
        $fiangonanaRepo = $this->createMock(EntityRepository::class);

        $membreRepo->method('findOneBy')->with(['email' => 'admin@test.com'])->willReturn(null);

        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Test Church');
        $fiangonanaRepo->method('findOneBy')->willReturn($fiangonana);

        $em->method('getRepository')->willReturnCallback(function ($class) use ($membreRepo, $fiangonanaRepo) {
            return match ($class) {
                Membre::class => $membreRepo,
                Fiangonana::class => $fiangonanaRepo,
                default => null,
            };
        });

        $passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->willReturn('hashed_password');

        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $command = new CreateAdminUserCommand($em, $passwordHasher);
        $application = new Application();
        $application->add($command);

        $commandTester = new CommandTester($application->find('app:create-admin'));
        $commandTester->execute([
            'email' => 'admin@test.com',
            'password' => 'secret123',
            'nom' => 'Ratsimba',
            'prenom' => 'Jean',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Compte administrateur "admin@test.com" créé avec succès !', $output);
    }
}
