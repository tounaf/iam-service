# Spécification Technique & Fonctionnelle de l'Administration Backoffice

Ce document décrit l'architecture, les fonctionnalités et la carte des routes du module d'administration backoffice de gestion paroissiale.

---

## 1. Vision & Objectifs

L'administration backoffice (`/admin`) offre un espace de gestion centralisé, moderne et sécurisé réservé aux dirigeants, secrétaires et responsables d'associations. Elle permet de :
* Suivre en temps réel les effectifs et indicateurs clés de la paroisse (`Fiangonana`), des zones géographiques (`Groupe`) et des associations.
* Gérer la paroisse (`Fiangonana`) et y rattacher / afficher directement ses **Zones & Groupes** et ses **Associations**.
* Gérer la liste dédiée des **Types d'Événements** (`TypeEvenement` : création, édition, suppression de catégories d'activités comme les Cultes, Formations, Asa, Réunions, Retraites) et attribuer un type lors de la création d'un **Événement** (`Evenement`).
* Consulter la **Fiche Détail d'un Événement** (`/admin/evenements/{id}`) :
  * Liste de tous les membres présents ayant pointé par scan QR Code.
  * Nombre total de présents et **taux de participation** (%) calculé dynamiquement par rapport aux membres de la Paroisse, du Groupe ou de l'Association.
  * Rédaction d'un **compte-rendu / résumé** détaillé des activités déroulées.
  * Ajout d'**appréciations et notes dynamiques** (ex: *Très bien*, *Mauvais*, *Excellent*, *Animé*, ou notes personnalisées).
  * Galerie et téléversement de **médias multiples** (photos et vidéos de l'événement).
* Inscrire et éditer les fiches des membres avec possibilité d'**attribution directe à une ou plusieurs associations** dès la création, et support de **téléchargement Glisser-Déposer (Drag-and-Drop)** pour la photo de profil.
* Gérer l'attribution des rôles contextuels temporaires (CRBAC) pour un membre : possibilité d'attribuer un rôle à une **Association**, une **Zone Géographique/Groupe** ou une **Paroisse/Fiangonana** avec des bornes temporelles précises (`startDate` et `endDate`, ex: du 20/01/2026 au 12/12/2026).
* Générer et imprimer les cartes d'identité membres officielles avec QR Code unique.
* Consulter les statistiques d'assiduité, les rapports d'activités, les taux de participation et les taux de retard des membres aux événements.
* Gérer les **Cotisations Mensuelles** (saisie en jusqu'à 4 tranches par mois avec suivi du nombre de mois réglés sur 12) et les **Dons Libres** dans l'onglet dédié du profil membre.
* Gérer les mandats et droits d'accès via le modèle de sécurité contextuel (CRBAC).

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
        Tab6[Onglet 6 : Événements & Présences - Carte cliquable vers Fiche Événement]
    end

    Tab6 --> EvenementDetail[/admin/evenements/id - Fiche Détail Événement]
    EvenementDetail --> CompteRendu[Compte-Rendu & Résumé]
    EvenementDetail --> NotesEval[Appréciations & Notes Dynamiques]
    EvenementDetail --> MediaUpload[Galerie Photos & Vidéos Souvenirs]
    EvenementDetail --> PresenceList[Membres Présents & Taux de Participation %]
```

---

## 3. Architecture Technique & Principes UI

* **Séparation MVC Strict** : Tout le rendu visuel est confiné dans le dossier `templates/admin/` sous forme de templates Twig responsives utilisant Tailwind CSS.
* **Fiche Détail d'un Événement (`AdminEvenementController`)** :
  * Calcul automatique du taux de participation : `(Nombre de Présents / Effectif Total du Périmètre) * 100`.
  * Support pour l'ajout dynamique de tags / appréciation de l'événement.
  * Téléversement de photos (`.jpg`, `.png`, `.webp`) et vidéos (`.mp4`, `.webm`) stockées dans `public/uploads/events/`.
* **Architecture des Controllers** :
  * `AdminDashboardController` (`/admin/dashboard`) : Vue synthétique et tableaux de bord KPI.
  * `AdminFiangonanaController` (`/admin/fiangonana`, `/admin/fiangonana/nouveau`, `/admin/fiangonana/{id}/editer`, `/admin/fiangonana/{id}/nouveau-groupe`, `/admin/fiangonana/{id}/nouvelle-association`, `/admin/fiangonana/{id}/nouvel-evenement`) : Gestion des paroisses.
  * `AdminGroupeController` (`/admin/groupes`, `/admin/groupes/nouveau`, `/admin/groupes/{id}/editer`, `/admin/groupes/{id}/nouvel-evenement`) : Gestion des zones géographiques.
  * `AdminAssociationController` (`/admin/associations`, `/admin/associations/nouveau`, `/admin/associations/{id}/editer`, `/admin/associations/{id}/nouvel-evenement`) : Gestion des associations.
  * `AdminTypeEvenementController` (`/admin/types-evenement`, `/admin/types-evenement/nouveau`, `/admin/types-evenement/{id}/editer`, `/admin/types-evenement/{id}/supprimer`) : Gestion des types d'événements.
  * `AdminEvenementController` (`/admin/evenements/{id}`, `/admin/evenements/{id}/update`) : Détail de l'événement, compte-rendu, notes, médias et assiduité.
  * `AdminRoleController` (`/admin/roles`) : Gestion des rôles applicatifs et permissions.
  * `AdminMembreController` (`/admin/membres`, `/admin/membres/nouveau`, `/admin/membres/{id}/editer`) : Inscription avec attribution directe d'associations, attribution de rôles contextuels temporaires (Association, Groupe, Fiangonana) liés dans le temps, photo drag-and-drop, carte membre et QR code.
  * `AdminPresenceController` (`/admin/presences`) : Consolidation des événements, historique des scans et rapports.

---

## 4. Cartographie des Routes Backoffice

```mermaid
graph TD
    Root[/admin] --> Dashboard[/admin/dashboard]
    Root --> Fiangonana[/admin/fiangonana]
    Root --> Groupes[/admin/groupes]
    Root --> Associations[/admin/associations]
    Root --> TypeEvenements[/admin/types-evenement]
    Root --> Roles[/admin/roles]
    Root --> Membres[/admin/membres]
    Root --> Presences[/admin/presences]

    FiangonanaEdit --> EventDetailParoisse[/admin/evenements/{id}]
    GroupeEdit --> EventDetailGroupe[/admin/evenements/{id}]
    AssociationEdit --> EventDetailAssociation[/admin/evenements/{id}]

    EventDetailParoisse --> UpdateEvent[/admin/evenements/{id}/update]
```
