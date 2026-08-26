# Gestion des Droits et Habilitations Basée sur l'Entité `Feature`

Ce document présente l'architecture de la gestion dynamique des droits et habilitations au sein de la plateforme. Il s'appuie sur l'entité **`Feature`** (Fonctionnalité) pour recenser l'ensemble des fonctionnalités, menus backoffice admin et actions du portal Espace Membre afin de rendre dynamique l'affichage, les menus, ainsi que le contrôle d'accès.

---

## 1. Vision et Objectifs

Dans notre architecture **Contextual Role-Based Access Control (CRBAC)** :
- Un **Membre** détient un ou plusieurs **Rôles** au sein d'un **Contexte** (Paroisse / Fiangonana, Zone / Groupe, Association, Sous-groupe).
- Un **Rôle** regroupe un ensemble de **Permissions**.
- Une **Permission** associe un `Role` à une **`Feature`** pour une `Action` donnée (`READ`, `WRITE`, `ADMIN`, `EXECUTE`, etc.).
- L'entité **`Feature`** recense la totalité des points d'entrée fonctionnels (menus d'administration, onglets/actions de l'Espace Membre, opérations d'API).

Grâce à cette centralisation, nous pouvons :
1. **Piloter dynamiquement la navigation** : Générer automatiquement les menus de la sidebar d'administration et les onglets / modules de l'Espace Membre selon les habilitations du membre connecté.
2. **Gérer finement les droits par rôle** : Permettre à un administrateur d'attribuer ou restreindre dynamiquement l'accès à n'importe quel menu ou action sans modifier le code source.
3. **Optimiser les performances grâce à la mise en cache** : Les fonctionnalités calculées lors d'une connexion réussie sont mises en cache. Toute modification de permission ne prend effet qu'après déconnexion puis nouvelle connexion du membre.

---

## 2. Modèle de Données & Entité `Feature`

### 2.1 Schéma Relationnel

```mermaid
erDiagram
    FEATURE {
        int id PK
        string code "ex: ADMIN_MENU_MEMBRES, MEMBER_EVENT_SCAN"
        string label "Libellé affichable"
        string category "ex: ADMIN_MENU, MEMBER_SPACE"
        string description "Description détaillée"
        string targetRoute "Route Symfony / URL React"
        string icon "Icône FontAwesome/Lucide"
        int sortOrder "Ordre d'affichage"
    }
    ROLE {
        int id PK
        string name "ex: PRESIDENT, TRESORIER, ADMIN"
        string description
    }
    PERMISSION {
        int id PK
        string action "ex: READ, WRITE, ADMIN"
    }
    ROLE_ASSIGNMENT {
        int id PK
        string exerciceYear
        boolean isActive
    }
    MEMBRE {
        int id PK
        string nom
        string prenom
    }

    ROLE ||--o{ PERMISSION : "contient"
    FEATURE ||--o{ PERMISSION : "est ciblée par"
    ROLE ||--o{ ROLE_ASSIGNMENT : "est attribué à"
    MEMBRE ||--o{ ROLE_ASSIGNMENT : "détient"
```

### 2.2 Propriétés de l'Entité `Feature`

| Champ | Type | Description | Exemple |
|---|---|---|---|
| `id` | `integer` | Identifiant unique généré. | `1` |
| `code` | `string(100)` | Code unique identifiant la fonctionnalité. | `ADMIN_MENU_MEMBRES` |
| `label` | `string(255)` | Nom lisible par l'utilisateur. | `Gestion des Membres` |
| `category` | `string(50)` | Catégorie/Zone (`ADMIN_MENU`, `MEMBER_SPACE`, `API_ACTION`). | `ADMIN_MENU` |
| `description` | `text` | Explication fonctionnelle. | `Permet d'accéder au CRUD des membres.` |
| `targetRoute` | `string(255)` | Route Symfony ou path SPA React associées. | `app_admin_membre_index` |
| `icon` | `string(100)` | Classe d'icône d'interface. | `fas fa-users` |
| `sortOrder` | `integer` | Ordre pour le rendu dynamique du menu. | `10` |

---

## 3. Cartographie Complète des `Features`

### 3.1 Menus du Backoffice Administration (`category: ADMIN_MENU`)

| Code Feature | Libellé | Route / URL | Description |
|---|---|---|---|
| `ADMIN_MENU_DASHBOARD` | Tableau de bord | `app_admin_dashboard` | Vue globale de l'administration et statistiques. |
| `ADMIN_MENU_FIANGONANA` | Paroisses / Fiangonana | `app_admin_fiangonana_index` | Gestion de l'entité paroissiale racine. |
| `ADMIN_MENU_GROUPES` | Zones / Groupes | `app_admin_groupe_index` | Gestion des zones géographiques et groupes. |
| `ADMIN_MENU_ASSOCIATIONS` | Associations | `app_admin_association_index` | Gestion des associations (Femmes, Hommes, Jeunesse...). |
| `ADMIN_MENU_MEMBRES` | Membres | `app_admin_membre_index` | Gestion globale de l'annuaire des membres. |
| `ADMIN_MENU_ROLES` | Rôles & Habilitations | `app_admin_role_index` | Gestion des rôles, attributions et permissions CRBAC. |
| `ADMIN_MENU_EVENEMENTS` | Événements | `app_admin_evenement_index` | Création et suivi des événements paroissiaux. |
| `ADMIN_MENU_TYPES_EVENEMENT` | Types d'Événements | `app_admin_type_evenement_index` | Catégorisation des événements (Culte, Réunion, Fête...). |
| `ADMIN_MENU_PRESENCES` | Suivi des Présences | `app_admin_presence_index` | Visualisation et gestion du pointage des présences. |

### 3.2 Actions & Vue de l'Espace Membre (`category: MEMBER_SPACE`)

| Code Feature | Libellé | Target Route / Path | Description |
|---|---|---|---|
| `MEMBER_PROFILE_VIEW` | Profil Membre | `/espace-membre/profile` | Consultation des informations personnelles et carte de membre. |
| `MEMBER_PROFILE_UPDATE` | Mise à jour Profil | `/api/membres/{id}/update-profile` | Modification du nom, prénom, email, téléphone et photo avatar. |
| `MEMBER_PASSWORD_CHANGE` | Changement Mot de passe | `/api/me/change-password` | Modification sécurisée de son propre mot de passe. |
| `MEMBER_CARTE_GENERATE` | Carte de Membre & QR Code | `/api/membres/{id}/carte` | Génération de la carte officielle avec QR code unique. |
| `MEMBER_EVENTS_VIEW` | Liste des Événements | `/espace-membre/events` | Consultation des événements à venir et passés. |
| `MEMBER_EVENT_CREATE` | Création d'Événement | `/api/member-events/create` | Saisie d'un nouvel événement par un membre responsable. |
| `MEMBER_EVENT_SCAN` | Scanner QR Code Présence | `/api/member-events/{id}/scan` | Scan caméra direct pour marquer la présence d'un membre. |
| `MEMBER_EVENT_ATTENDEES` | Liste des Présents | `/api/member-events/{id}/attendees` | Consultation en temps réel des membres présents à un événement. |
| `MEMBER_EVENT_NOTE_ADD` | Ajouter une Note/Évaluation | `/api/member-events/{id}/add-note` | Ajout de remarques ou évaluations sur un événement. |
| `MEMBER_EVENT_COMPTE_RENDU` | Compte Rendu | `/api/member-events/{id}/compte-rendu` | Rédaction / mise à jour du procès-verbal ou résumé d'événement. |
| `MEMBER_EVENT_MEDIA_UPLOAD` | Envoi Photos & Vidéos | `/api/member-events/{id}/upload-media` | Téléversement de photos/vidéos associées à un événement. |
| `MEMBER_AFFILIATIONS_VIEW` | Mes Affiliations & Groupes | `/espace-membre/affiliations` | Consultation des associations et zones du membre. |
| `MEMBER_AFFILIATION_MANAGE` | Gestion des Membres d'Association | `/api/association-membres/save` | Ajout / modification des membres au sein de son association. |
| `MEMBER_STATS_VIEW` | Taux de Participation | `/api/membres/{id}/participation-stats` | Suivi annuel des statistiques d'assiduité et présences. |

---

## 4. Stratégie de Cache & Cycle de Vie des Habilitations

Pour alléger la charge sur la base de données et accélérer la vérification des droits sur chaque requête, le système s'appuie sur le composant **Symfony Cache** via `App\Service\PermissionResolver` et `App\EventListener\SecurityCacheListener`.

```mermaid
sequenceDiagram
    autonumber
    actor Membre as Membre Authentifié
    participant EventListener as SecurityCacheListener
    participant Resolver as PermissionResolver
    participant Cache as Symfony Cache
    participant DB as Base de Données

    Note over Membre,DB: 1. Connexion réussie (Login)
    Membre->>EventListener: LoginSuccessEvent
    EventListener->>Resolver: getGrantedFeatures(Membre)
    Resolver->>DB: Reconstitution des permissions via RoleAssignments
    DB-->>Resolver: [ADMIN_MENU_MEMBRES, MEMBER_EVENT_SCAN, ...]
    Resolver->>Cache: Enregistre la clé `user_features_{id}`

    Note over Membre,DB: 2. Requêtes ultérieures (Backoffice / API)
    Membre->>Resolver: isFeatureGranted('ADMIN_MENU_MEMBRES')
    Resolver->>Cache: Récupération directe depuis la clé `user_features_{id}`
    Cache-->>Resolver: Features autorisées

    Note over Membre,DB: 3. Déconnexion (Logout)
    Membre->>EventListener: LogoutEvent
    EventListener->>Resolver: invalidateCache(Membre)
    Resolver->>Cache: Suppression de la clé `user_features_{id}`
```

### Principes Clés du Cache :
1. **Création au Login** : Dès qu'une connexion réussie se produit (`LoginSuccessEvent`), `PermissionResolver` calcule l'ensemble des fonctionnalités autorisées pour le membre et les stocke dans le cache sous la clé `user_features_{id}`.
2. **Utilisation sur chaque requête** :
   - Dans **Twig** (Admin Backoffice) : Les fonctions `is_feature_granted('FEATURE_CODE')` et `get_granted_features()` vérifient la présence de la fonctionnalité dans le cache.
   - Dans l'**API / Espace Membre React** : Les endpoints `/api/login_check` et `/api/me` renvoient les `features` directement depuis le cache.
3. **Invalidation au Logout** : Lors de la déconnexion (`LogoutEvent`), la clé de cache du membre est automatiquement supprimée (`invalidateCache`).
4. **Prise en compte des modifications de permissions** : Toute modification apportée aux rôles ou permissions d'un membre en base de données ne sera effective qu'à partir de sa prochaine connexion (après sa déconnexion).

---

## 5. Administration & Outils CLI

### 5.1 Commande CLI : `app:seed-features`

Pour approvisionner automatiquement l'ensemble des fonctionnalités prédéfinies en base de données :

```bash
php bin/console app:seed-features
```

Option de purge préalable :
```bash
php bin/console app:seed-features --purge
```

### 5.2 Commande CLI : `app:grant-admin`

Pour affecter rapidement les droits d'administration à un membre existant via la ligne de commande :

```bash
php bin/console app:grant-admin membre@paroisse.mg
```

Cette commande recherche le membre par son adresse email et lui attribue le rôle `ROLE_ADMIN`.

### 5.3 Interfaces d'Administration

1. **Gestion des Fonctionnalités (`/admin/features`)** :
   - Liste, création, modification et suppression des entités `Feature`.
   - Configuration du `code`, `label`, `category`, `targetRoute`, `icon` et `sortOrder`.
2. **Matrice des Permissions par Rôle (`/admin/roles/{id}/permissions`)** :
   - Interface graphique sous forme de grille pour attribuer/retirer les permissions (`READ`, `WRITE`, `ADMIN`, `EXECUTE`) de chaque `Feature` pour un `Role` sélectionné.
