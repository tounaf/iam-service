# Spécification Technique & Fonctionnelle de l'Administration Backoffice

Ce document décrit l'architecture, les fonctionnalités et la carte des routes du module d'administration backoffice de gestion paroissiale.

---

## 1. Vision & Objectifs

L'administration backoffice (`/admin`) offre un espace de gestion centralisé, moderne et sécurisé réservé aux dirigeants, secrétaires et responsables d'associations. Elle permet de :
* Suivre en temps réel les effectifs et indicateurs clés de la paroisse (`Fiangonana`), des zones géographiques (`Groupe`) et des associations.
* Gérer la paroisse (`Fiangonana`) et y rattacher / afficher directement ses **Zones & Groupes** et ses **Associations**.
* Gérer les **Types d'Événements** (`TypeEvenement` : Cultes, Formations des Jeunes, Asa, Réunions) et créer des **Événements** (`Evenement`) typés pour chaque Paroisse, Groupe/Zone ou Association.
* Inscrire et éditer les fiches des membres avec support de **téléchargement Glisser-Déposer (Drag-and-Drop)** pour la photo de profil.
* Générer et imprimer les cartes d'identité membres officielles avec QR Code unique.
* Consulter les statistiques d'assiduité, les rapports d'activités et les taux de participation annuels.
* Gérer les mandats et droits d'accès via le modèle de sécurité contextuel (CRBAC).
* Administrer les structures (Paroisses, Zones, Associations) avec onglets dédiés pour :
  * **Informations Générales**.
  * **Zones & Groupes** (avec formulaire de création rapide direct).
  * **Associations Paroissiales** (avec formulaire de création rapide direct).
  * **Liste des membres rattachés**.
  * **Comités & Bureau (Président, Secrétaire, Trésorier)** via les attributions de rôles CRBAC.
  * **Gestion des événements & Présences associées** (création d'événements typés et pointages scannés).

---

## 2. Diagramme de Concepteur & Architecture des Onglets (Mermaid)

```mermaid
graph TD
    subgraph Structure[Entité Paroisse : Fiangonana]
        Tab1[Onglet 1 : Informations Générales]
        Tab2[Onglet 2 : Zones & Groupes - Affichage + Formulaire d'ajout direct]
        Tab3[Onglet 3 : Associations - Affichage + Formulaire d'ajout direct]
        Tab4[Onglet 4 : Liste des Membres Inscrits]
        Tab5[Onglet 5 : Bureau & Comités CRBAC - Président, Secrétaire, Trésorier...]
        Tab6[Onglet 6 : Événements & Présences - Création d'événements typés + Pointages Scannés]
    end

    Tab2 --> GroupeEntity[Entité Groupe]
    Tab3 --> AssociationEntity[Entité Association]
    Tab4 --> MembreEntity[Entité Membre + Drag and Drop Photo Upload]
    Tab5 --> RoleAssignmentEntity[Entité RoleAssignment & Role]
    Tab6 --> EvenementEntity[Entité Evenement]
    EvenementEntity --> TypeEvenementEntity[Entité TypeEvenement]
    Tab6 --> PresenceEntity[Entité Presence & Pointages Scannés]
```

---

## 3. Architecture Technique & Principes UI

* **Séparation MVC Strict** : Tout le rendu visuel est confiné dans le dossier `templates/admin/` sous forme de templates Twig responsives utilisant Tailwind CSS.
* **Gestion des Médias / Photos** : Formulaire multipart avec zone interactive de Glisser-Déposer (Drag and Drop) permettant d'uploader des photos de profil stockées sous `public/uploads/membres/`.
* **Gestion des Types d'Événements (`TypeEvenement`) & Événements (`Evenement`)** : Catégorisation dynamique des événements par types (Cultes, Formations, Asa, Réunions).
* **Architecture des Controllers** :
  * `AdminDashboardController` (`/admin/dashboard`) : Vue synthétique et tableaux de bord KPI.
  * `AdminFiangonanaController` (`/admin/fiangonana`, `/admin/fiangonana/nouveau`, `/admin/fiangonana/{id}/editer`, `/admin/fiangonana/{id}/nouveau-groupe`, `/admin/fiangonana/{id}/nouvelle-association`, `/admin/fiangonana/{id}/nouvel-evenement`) : Gestion des paroisses avec leurs groupes, associations, membres, bureau et événements typés.
  * `AdminGroupeController` (`/admin/groupes`, `/admin/groupes/nouveau`, `/admin/groupes/{id}/editer`, `/admin/groupes/{id}/nouvel-evenement`) : Gestion des zones géographiques avec membres, bureau et événements typés.
  * `AdminAssociationController` (`/admin/associations`, `/admin/associations/nouveau`, `/admin/associations/{id}/editer`, `/admin/associations/{id}/nouvel-evenement`) : Gestion des associations avec membres, comités et événements typés.
  * `AdminRoleController` (`/admin/roles`) : Gestion des rôles applicatifs et permissions.
  * `AdminMembreController` (`/admin/membres`, `/admin/membres/nouveau`, `/admin/membres/{id}/editer`) : Inscription, modification, upload de photo en drag-and-drop, carte membre, QR code et présences.
  * `AdminPresenceController` (`/admin/presences`) : Consolidation des événements, historique des scans et rapports de participation.

---

## 4. Cartographie des Routes Backoffice

```mermaid
graph TD
    Root[/admin] --> Dashboard[/admin/dashboard]
    Root --> Fiangonana[/admin/fiangonana]
    Root --> Groupes[/admin/groupes]
    Root --> Associations[/admin/associations]
    Root --> Roles[/admin/roles]
    Root --> Membres[/admin/membres]
    Root --> Presences[/admin/presences]

    Fiangonana --> FiangonanaEdit[/admin/fiangonana/{id}/editer - Tabs: Groupes, Associations, Membres, Bureau, Événements]
    FiangonanaEdit --> AddGroupe[/admin/fiangonana/{id}/nouveau-groupe]
    FiangonanaEdit --> AddAssociation[/admin/fiangonana/{id}/nouvelle-association]
    FiangonanaEdit --> AddEvenementFiangonana[/admin/fiangonana/{id}/nouvel-evenement]

    Groupes --> GroupeEdit[/admin/groupes/{id}/editer - Tabs: Membres, Bureau, Événements]
    GroupeEdit --> AddEvenementGroupe[/admin/groupes/{id}/nouvel-evenement]

    Associations --> AssociationEdit[/admin/associations/{id}/editer - Tabs: Membres, Bureau, Événements]
    AssociationEdit --> AddEvenementAssociation[/admin/associations/{id}/nouvel-evenement]

    Membres --> MembreEdit[/admin/membres/{id}/editer - Photo Drag-and-Drop + Tabs: Général, Roles CRBAC, Présences]
```
