<?php

namespace App\Tests\Command;

use App\Command\GrantAdminCommand;
use App\Entity\Membre;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class GrantAdminCommandTest extends TestCase
{
    public function testExecutePromotesMemberToAdmin(): void
    {
        $membre = new Membre();
        $membre->setEmail('user@example.com');
        $membre->setRoles(['ROLE_USER']);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->with(['email' => 'user@example.com'])->willReturn($membre);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(Membre::class)->willReturn($repo);
        $em->expects($this->once())->method('flush');

        $command = new GrantAdminCommand($em);
        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find('app:grant-admin'));
        $statusCode = $commandTester->execute(['email' => 'user@example.com']);

        $this->assertEquals(0, $statusCode);
        $this->assertContains('ROLE_ADMIN', $membre->getRoles());
        $this->assertStringContainsString('Les droits administrateur (ROLE_ADMIN) ont été attribués avec succès', $commandTester->getDisplay());
    }

    public function testExecuteFailsWhenMemberNotFound(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->with(['email' => 'unknown@example.com'])->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(Membre::class)->willReturn($repo);

        $command = new GrantAdminCommand($em);
        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find('app:grant-admin'));
        $statusCode = $commandTester->execute(['email' => 'unknown@example.com']);

        $this->assertEquals(1, $statusCode);
        $this->assertStringContainsString('Aucun membre trouvé avec l\'adresse email', $commandTester->getDisplay());
    }
}
