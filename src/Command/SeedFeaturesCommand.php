<?php

namespace App\Command;

use App\Entity\Feature;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-features',
    description: 'Approvisionne et synchronise toutes les fonctionnalités (features) système dans la base de données',
)]
class SeedFeaturesCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this->addOption('purge', null, InputOption::VALUE_NONE, 'Purge les fonctionnalités existantes avant l\'approvisionnement');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $purge = (bool) $input->getOption('purge');

        $repo = $this->entityManager->getRepository(Feature::class);

        if ($purge) {
            $existing = $repo->findAll();
            foreach ($existing as $item) {
                $this->entityManager->remove($item);
            }
            $this->entityManager->flush();
            $io->note('Toutes les fonctionnalités existantes ont été purgées.');
        }

        $defaultFeatures = [
            // Admin Menus
            [
                'code' => 'ADMIN_MENU_DASHBOARD',
                'label' => 'Tableau de bord',
                'category' => 'ADMIN_MENU',
                'description' => 'Vue globale de l\'administration et statistiques',
                'targetRoute' => 'admin_dashboard',
                'icon' => 'fa-solid fa-chart-pie',
                'sortOrder' => 10,
            ],
            [
                'code' => 'ADMIN_MENU_FIANGONANA',
                'label' => 'Paroisses (Fiangonana)',
                'category' => 'ADMIN_MENU',
                'description' => 'Gestion des paroisses et de la hiérarchie locale',
                'targetRoute' => 'admin_fiangonana_index',
                'icon' => 'fa-solid fa-church',
                'sortOrder' => 20,
            ],
            [
                'code' => 'ADMIN_MENU_GROUPES',
                'label' => 'Zones (Groupes)',
                'category' => 'ADMIN_MENU',
                'description' => 'Gestion des zones géographiques et groupes',
                'targetRoute' => 'admin_groupe_index',
                'icon' => 'fa-solid fa-map-location-dot',
                'sortOrder' => 30,
            ],
            [
                'code' => 'ADMIN_MENU_ASSOCIATIONS',
                'label' => 'Associations',
                'category' => 'ADMIN_MENU',
                'description' => 'Gestion des associations de la paroisse',
                'targetRoute' => 'admin_association_index',
                'icon' => 'fa-solid fa-people-group',
                'sortOrder' => 40,
            ],
            [
                'code' => 'ADMIN_MENU_TYPES_EVENEMENT',
                'label' => 'Types d\'Événements',
                'category' => 'ADMIN_MENU',
                'description' => 'Catégorisation des événements (Culte, Réunion...)',
                'targetRoute' => 'admin_type_evenement_index',
                'icon' => 'fa-solid fa-tags',
                'sortOrder' => 50,
            ],
            [
                'code' => 'ADMIN_MENU_ROLES',
                'label' => 'Rôles & Sécurité',
                'category' => 'ADMIN_MENU',
                'description' => 'Gestion des rôles, permissions et habilitations CRBAC',
                'targetRoute' => 'admin_role_index',
                'icon' => 'fa-solid fa-user-shield',
                'sortOrder' => 60,
            ],
            [
                'code' => 'ADMIN_MENU_MEMBRES',
                'label' => 'Membres',
                'category' => 'ADMIN_MENU',
                'description' => 'Gestion de l\'annuaire général des membres',
                'targetRoute' => 'admin_membre_index',
                'icon' => 'fa-solid fa-users',
                'sortOrder' => 70,
            ],
            [
                'code' => 'ADMIN_MENU_PRESENCES',
                'label' => 'Présences & Scans',
                'category' => 'ADMIN_MENU',
                'description' => 'Suivi du pointage et historiques des présences',
                'targetRoute' => 'admin_presences',
                'icon' => 'fa-solid fa-qrcode',
                'sortOrder' => 80,
            ],

            // Espace Membre
            [
                'code' => 'MEMBER_PROFILE_VIEW',
                'label' => 'Profil Membre',
                'category' => 'MEMBER_SPACE',
                'description' => 'Consultation des informations personnelles et carte',
                'targetRoute' => '/espace-membre/profile',
                'icon' => 'fa-solid fa-user',
                'sortOrder' => 100,
            ],
            [
                'code' => 'MEMBER_PROFILE_UPDATE',
                'label' => 'Mise à jour Profil',
                'category' => 'MEMBER_SPACE',
                'description' => 'Modification de la fiche et avatar du membre',
                'targetRoute' => '/api/membres/{id}/update-profile',
                'icon' => 'fa-solid fa-user-pen',
                'sortOrder' => 110,
            ],
            [
                'code' => 'MEMBER_PASSWORD_CHANGE',
                'label' => 'Changement Mot de passe',
                'category' => 'MEMBER_SPACE',
                'description' => 'Modification sécurisée du mot de passe',
                'targetRoute' => '/api/me/change-password',
                'icon' => 'fa-solid fa-key',
                'sortOrder' => 120,
            ],
            [
                'code' => 'MEMBER_CARTE_GENERATE',
                'label' => 'Carte de Membre & QR Code',
                'category' => 'MEMBER_SPACE',
                'description' => 'Génération et impression de la carte officielle avec QR Code',
                'targetRoute' => '/api/membres/{id}/carte',
                'icon' => 'fa-solid fa-id-card',
                'sortOrder' => 130,
            ],
            [
                'code' => 'MEMBER_EVENTS_VIEW',
                'label' => 'Liste des Événements',
                'category' => 'MEMBER_SPACE',
                'description' => 'Consultation de l\'agenda et liste des événements',
                'targetRoute' => '/espace-membre/events',
                'icon' => 'fa-solid fa-calendar-days',
                'sortOrder' => 140,
            ],
            [
                'code' => 'MEMBER_EVENT_CREATE',
                'label' => 'Création d\'Événement',
                'category' => 'MEMBER_SPACE',
                'description' => 'Saisie et planification d\'un nouvel événement',
                'targetRoute' => '/api/member-events/create',
                'icon' => 'fa-solid fa-calendar-plus',
                'sortOrder' => 150,
            ],
            [
                'code' => 'MEMBER_EVENT_SCAN',
                'label' => 'Scanner QR Code Présence',
                'category' => 'MEMBER_SPACE',
                'description' => 'Scan caméra direct pour valider la présence lors d\'un événement',
                'targetRoute' => '/api/member-events/{id}/scan',
                'icon' => 'fa-solid fa-camera-retro',
                'sortOrder' => 160,
            ],
            [
                'code' => 'MEMBER_EVENT_ATTENDEES',
                'label' => 'Liste des Présents',
                'category' => 'MEMBER_SPACE',
                'description' => 'Consultation des membres pointés présents à un événement',
                'targetRoute' => '/api/member-events/{id}/attendees',
                'icon' => 'fa-solid fa-clipboard-user',
                'sortOrder' => 170,
            ],
            [
                'code' => 'MEMBER_EVENT_NOTE_ADD',
                'label' => 'Ajouter une Note/Évaluation',
                'category' => 'MEMBER_SPACE',
                'description' => 'Ajout de remarques ou notes sur un événement',
                'targetRoute' => '/api/member-events/{id}/add-note',
                'icon' => 'fa-solid fa-note-sticky',
                'sortOrder' => 180,
            ],
            [
                'code' => 'MEMBER_EVENT_COMPTE_RENDU',
                'label' => 'Compte Rendu Événement',
                'category' => 'MEMBER_SPACE',
                'description' => 'Rédaction du compte rendu ou résumé d\'un événement',
                'targetRoute' => '/api/member-events/{id}/compte-rendu',
                'icon' => 'fa-solid fa-file-lines',
                'sortOrder' => 190,
            ],
            [
                'code' => 'MEMBER_EVENT_MEDIA_UPLOAD',
                'label' => 'Envoi Photos & Vidéos',
                'category' => 'MEMBER_SPACE',
                'description' => 'Téléversement de médias sur un événement',
                'targetRoute' => '/api/member-events/{id}/upload-media',
                'icon' => 'fa-solid fa-photo-film',
                'sortOrder' => 200,
            ],
            [
                'code' => 'MEMBER_AFFILIATIONS_VIEW',
                'label' => 'Mes Affiliations & Groupes',
                'category' => 'MEMBER_SPACE',
                'description' => 'Consultation des affiliations aux associations et zones',
                'targetRoute' => '/espace-membre/affiliations',
                'icon' => 'fa-solid fa-sitemap',
                'sortOrder' => 210,
            ],
            [
                'code' => 'MEMBER_AFFILIATION_MANAGE',
                'label' => 'Gestion Membres Association',
                'category' => 'MEMBER_SPACE',
                'description' => 'Gestion des membres affiliés à son association',
                'targetRoute' => '/api/association-membres/save',
                'icon' => 'fa-solid fa-user-plus',
                'sortOrder' => 220,
            ],
            [
                'code' => 'MEMBER_STATS_VIEW',
                'label' => 'Statistiques de Participation',
                'category' => 'MEMBER_SPACE',
                'description' => 'Consultation du taux d\'assiduité et présence',
                'targetRoute' => '/api/membres/{id}/participation-stats',
                'icon' => 'fa-solid fa-chart-line',
                'sortOrder' => 230,
            ],
        ];

        $createdCount = 0;
        $updatedCount = 0;

        foreach ($defaultFeatures as $data) {
            $feature = $repo->findOneBy(['code' => $data['code']]);
            if (!$feature) {
                $feature = new Feature();
                $feature->setCode($data['code']);
                $createdCount++;
            } else {
                $updatedCount++;
            }

            $feature->setLabel($data['label']);
            $feature->setCategory($data['category']);
            $feature->setDescription($data['description']);
            $feature->setTargetRoute($data['targetRoute']);
            $feature->setIcon($data['icon']);
            $feature->setSortOrder($data['sortOrder']);

            $this->entityManager->persist($feature);
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Approvisionnement terminé avec succès : %d fonctionnalité(s) créée(s), %d mise(s) à jour.',
            $createdCount,
            $updatedCount
        ));

        return Command::SUCCESS;
    }
}
