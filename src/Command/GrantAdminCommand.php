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

#[AsCommand(
    name: 'app:grant-admin',
    description: 'Affecte les droits administrateur (ROLE_ADMIN) à un membre par son email',
)]
class GrantAdminCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'L\'adresse email du membre à promouvoir en administrateur');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = trim((string) $input->getArgument('email'));

        $membreRepository = $this->entityManager->getRepository(Membre::class);
        $membre = $membreRepository->findOneBy(['email' => $email]);

        if (!$membre) {
            $io->error(sprintf('Aucun membre trouvé avec l\'adresse email "%s".', $email));
            return Command::FAILURE;
        }

        $roles = $membre->getRoles();
        if (in_array('ROLE_ADMIN', $roles, true)) {
            $io->note(sprintf('Le membre "%s %s" (%s) possède déjà les droits administrateur.', $membre->getPrenom(), $membre->getNom(), $email));
            return Command::SUCCESS;
        }

        $roles[] = 'ROLE_ADMIN';
        $membre->setRoles(array_unique($roles));

        $this->entityManager->flush();

        $io->success(sprintf('Les droits administrateur (ROLE_ADMIN) ont été attribués avec succès à "%s %s" (%s).', $membre->getPrenom(), $membre->getNom(), $email));

        return Command::SUCCESS;
    }
}
