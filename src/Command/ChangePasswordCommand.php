<?php

namespace App\Command;

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
    name: 'app:change-password',
    description: 'Modifie le mot de passe d\'un membre/utilisateur via son email',
)]
class ChangePasswordCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'L\'adresse email du membre');
        $this->addArgument('new-password', InputArgument::REQUIRED, 'Le nouveau mot de passe');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email');
        $plainPassword = $input->getArgument('new-password');

        $membreRepository = $this->entityManager->getRepository(Membre::class);
        $membre = $membreRepository->findOneBy(['email' => $email]);

        if (!$membre) {
            $io->error(sprintf('Aucun membre trouvé avec l\'adresse email "%s".', $email));
            return Command::FAILURE;
        }

        $hashedPassword = $this->passwordHasher->hashPassword($membre, $plainPassword);
        $membre->setPassword($hashedPassword);

        $this->entityManager->flush();

        $io->success(sprintf('Le mot de passe pour le membre "%s" a été modifié avec succès !', $email));

        return Command::SUCCESS;
    }
}
