<?php

namespace App\Tests\Command;

use App\Command\ChangePasswordCommand;
use App\Entity\Membre;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ChangePasswordCommandTest extends TestCase
{
    public function testChangePasswordCommandUpdatesPassword(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $membreRepo = $this->createMock(EntityRepository::class);

        $membre = new Membre();
        $membre->setEmail('test@example.com');

        $membreRepo->method('findOneBy')->with(['email' => 'test@example.com'])->willReturn($membre);

        $em->method('getRepository')->with(Membre::class)->willReturn($membreRepo);

        $passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->with($membre, 'newsecret123')
            ->willReturn('new_hashed_password');

        $em->expects($this->once())->method('flush');

        $command = new ChangePasswordCommand($em, $passwordHasher);
        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find('app:change-password'));
        $commandTester->execute([
            'email' => 'test@example.com',
            'new-password' => 'newsecret123',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('test@example.com', $output);
        $this->assertEquals('new_hashed_password', $membre->getPassword());
    }
}
