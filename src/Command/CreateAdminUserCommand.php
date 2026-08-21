<?php

namespace App\Command;

use App\Entity\Fiangonana;
use App\Entity\Membre;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée un membre administrateur avec les rôles ROLE_ADMIN',
)]
class CreateAdminUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'L\'adresse email de l\'administrateur');
        $this->addArgument('password', InputArgument::REQUIRED, 'Le mot de passe de l\'administrateur');
        $this->addArgument('nom', InputArgument::OPTIONAL, 'Le nom de l\'administrateur', 'Admin');
        $this->addArgument('prenom', InputArgument::OPTIONAL, 'Le prénom de l\'administrateur', 'Super');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email');
        $plainPassword = $input->getArgument('password');
        $nom = $input->getArgument('nom');
        $prenom = $input->getArgument('prenom');

        $membreRepository = $this->entityManager->getRepository(Membre::class);
        $existingMembre = $membreRepository->findOneBy(['email' => $email]);

        if ($existingMembre) {
            $existingMembre->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
            $hashedPassword = $this->passwordHasher->hashPassword($existingMembre, $plainPassword);
            $existingMembre->setPassword($hashedPassword);

            $this->entityManager->flush();

            $io->success(sprintf('Le membre existant "%s" a été promu Administrateur avec succès.', $email));
            return Command::SUCCESS;
        }

        // Fetch or create a default parish (Fiangonana) if none exists
        $fiangonanaRepository = $this->entityManager->getRepository(Fiangonana::class);
        $fiangonana = $fiangonanaRepository->findOneBy([]);

        if (!$fiangonana) {
            $fiangonana = new Fiangonana();
            $fiangonana->setNom('Paroisse Principale');
            $fiangonana->setCode('PAR-001');
            $this->entityManager->persist($fiangonana);
        }

        $admin = new Membre();
        $admin->setEmail($email);
        $admin->setNom($nom);
        $admin->setPrenom($prenom);
        $admin->setFiangonana($fiangonana);
        $admin->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        $admin->setQrCodeToken(bin2hex(random_bytes(16)));

        $hashedPassword = $this->passwordHasher->hashPassword($admin, $plainPassword);
        $admin->setPassword($hashedPassword);

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $io->success(sprintf('Compte administrateur "%s" créé avec succès !', $email));

        return Command::SUCCESS;
    }
}
