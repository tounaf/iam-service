<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Fully adapted to MySQL!
 */
final class Version20260806105724 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial migration for Parish organization with MySQL dialect.';
    }

    public function up(Schema $schema): void
    {
        // Table: fiangonana
        $this->addSql('CREATE TABLE fiangonana (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, code VARCHAR(50) NOT NULL, UNIQUE INDEX UNIQ_55CF559D77153098 (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: association
        $this->addSql('CREATE TABLE association (id INT AUTO_INCREMENT NOT NULL, fiangonana_id INT NOT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, INDEX IDX_FD8521CC5F9D98F6 (fiangonana_id), PRIMARY KEY(id), CONSTRAINT FK_FD8521CC5F9D98F6 FOREIGN KEY (fiangonana_id) REFERENCES fiangonana (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: feature
        $this->addSql('CREATE TABLE feature (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(100) NOT NULL, label VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_1FD7756677153098 (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: groupe
        $this->addSql('CREATE TABLE groupe (id INT AUTO_INCREMENT NOT NULL, fiangonana_id INT NOT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, INDEX IDX_4B98C215F9D98F6 (fiangonana_id), PRIMARY KEY(id), CONSTRAINT FK_4B98C215F9D98F6 FOREIGN KEY (fiangonana_id) REFERENCES fiangonana (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: membre
        $this->addSql('CREATE TABLE membre (id INT AUTO_INCREMENT NOT NULL, zone_geographique_id INT DEFAULT NULL, fiangonana_id INT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, email VARCHAR(180) NOT NULL, telephone VARCHAR(50) DEFAULT NULL, date_naissance DATETIME DEFAULT NULL, password VARCHAR(255) DEFAULT NULL, roles LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', UNIQUE INDEX UNIQ_F6B4FB29E7927C74 (email), INDEX IDX_F6B4FB29355BDC74 (zone_geographique_id), INDEX IDX_F6B4FB295F9D98F6 (fiangonana_id), PRIMARY KEY(id), CONSTRAINT FK_F6B4FB29355BDC74 FOREIGN KEY (zone_geographique_id) REFERENCES groupe (id) ON DELETE SET NULL, CONSTRAINT FK_F6B4FB295F9D98F6 FOREIGN KEY (fiangonana_id) REFERENCES fiangonana (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: membre_association
        $this->addSql('CREATE TABLE membre_association (membre_id INT NOT NULL, association_id INT NOT NULL, INDEX IDX_EEB303206A99F74A (membre_id), INDEX IDX_EEB30320EFB9C8A5 (association_id), PRIMARY KEY(membre_id, association_id), CONSTRAINT FK_EEB303206A99F74A FOREIGN KEY (membre_id) REFERENCES membre (id) ON DELETE CASCADE, CONSTRAINT FK_EEB30320EFB9C8A5 FOREIGN KEY (association_id) REFERENCES association (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: role
        $this->addSql('CREATE TABLE role (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_57698A6A5E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: sous_groupe
        $this->addSql('CREATE TABLE sous_groupe (id INT AUTO_INCREMENT NOT NULL, association_id INT NOT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, INDEX IDX_D4A67ED6EFB9C8A5 (association_id), PRIMARY KEY(id), CONSTRAINT FK_D4A67ED6EFB9C8A5 FOREIGN KEY (association_id) REFERENCES association (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: permission
        $this->addSql('CREATE TABLE permission (id INT AUTO_INCREMENT NOT NULL, role_id INT NOT NULL, feature_id INT NOT NULL, `action` VARCHAR(50) NOT NULL, INDEX IDX_E04992AAD60322AC (role_id), INDEX IDX_E04992AA60E4B879 (feature_id), PRIMARY KEY(id), CONSTRAINT FK_E04992AAD60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE, CONSTRAINT FK_E04992AA60E4B879 FOREIGN KEY (feature_id) REFERENCES feature (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: role_assignment
        $this->addSql('CREATE TABLE role_assignment (id INT AUTO_INCREMENT NOT NULL, membre_id INT NOT NULL, role_id INT NOT NULL, fiangonana_context_id INT DEFAULT NULL, groupe_context_id INT DEFAULT NULL, association_context_id INT DEFAULT NULL, sous_groupe_context_id INT DEFAULT NULL, start_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', end_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', exercice_year VARCHAR(10) NOT NULL, is_active TINYINT(1) NOT NULL, INDEX IDX_BFF12E966A99F74A (membre_id), INDEX IDX_BFF12E96D60322AC (role_id), INDEX IDX_BFF12E963F4DF1D3 (fiangonana_context_id), INDEX IDX_BFF12E96D3EDF013 (groupe_context_id), INDEX IDX_BFF12E964BCBA375 (association_context_id), INDEX IDX_BFF12E9612C825E4 (sous_groupe_context_id), PRIMARY KEY(id), CONSTRAINT FK_BFF12E966A99F74A FOREIGN KEY (membre_id) REFERENCES membre (id) ON DELETE CASCADE, CONSTRAINT FK_BFF12E96D60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE, CONSTRAINT FK_BFF12E963F4DF1D3 FOREIGN KEY (fiangonana_context_id) REFERENCES fiangonana (id) ON DELETE SET NULL, CONSTRAINT FK_BFF12E96D3EDF013 FOREIGN KEY (groupe_context_id) REFERENCES groupe (id) ON DELETE SET NULL, CONSTRAINT FK_BFF12E964BCBA375 FOREIGN KEY (association_context_id) REFERENCES association (id) ON DELETE SET NULL, CONSTRAINT FK_BFF12E9612C825E4 FOREIGN KEY (sous_groupe_context_id) REFERENCES sous_groupe (id) ON DELETE SET NULL) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: messenger_messages
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS messenger_messages');
        $this->addSql('DROP TABLE IF EXISTS role_assignment');
        $this->addSql('DROP TABLE IF EXISTS permission');
        $this->addSql('DROP TABLE IF EXISTS sous_groupe');
        $this->addSql('DROP TABLE IF EXISTS role');
        $this->addSql('DROP TABLE IF EXISTS membre_association');
        $this->addSql('DROP TABLE IF EXISTS membre');
        $this->addSql('DROP TABLE IF EXISTS groupe');
        $this->addSql('DROP TABLE IF EXISTS feature');
        $this->addSql('DROP TABLE IF EXISTS association');
        $this->addSql('DROP TABLE IF EXISTS fiangonana');
    }
}
