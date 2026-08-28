# Documentation d'Implémentation & Spécification des APIs

Ce document détaille l'implémentation technique de la structure paroissiale et du système de sécurité contextuelle (CRBAC), ainsi que la liste exhaustive des endpoints d'API exposés et consommables.

---

## 1. Architecture Technique & Choix Technologiques

*   **Framework Principal** : Symfony 7.4 (et rétro-compatible PHP 8.3/8.4 grâce à des polyfills natifs).
*   **Moteur d'API** : **API Platform 4.0**, garantissant une génération rapide, robuste et standardisée de ressources RESTful conformes aux spécifications OpenAPI, JSON-LD, Hydra et JSON.
*   **Base de Données** : **MySQL 8.x / MariaDB** (gérée via Doctrine ORM 2.20 avec migrations historisées).
*   **Système d'Habilitation** : **Symfony Security Voter** appliquant le modèle **CRBAC** (Contextual Role-Based Access Control) piloté à 100% par la base de données.

---

## 2. Modèle de Données & Tables Implémentées

Le schéma est composé de 9 entités interconnectées :

1.  **Fiangonana (fiangonana)** : L'entité paroissiale racine.
2.  **Membre (membre)** : Personnes physiques (implémente également `UserInterface` pour l'authentification).
3.  **Groupe (groupe)** : Zones géographiques de résidence (un membre appartient à max 1 zone géographique).
4.  **Association (association)** : Structures internes (un membre peut adhérer à plusieurs associations).
5.  **SousGroupe (sous_groupe)** : Cellules de travail ou comités rattachés à une association.
6.  **Feature (feature)** : Fonctionnalités applicatives ciblées par la sécurité (ex: `FINANCES`, `MEMBERSHIP`).
7.  **Role (role)** : Rôles définis (ex: `PRESIDENT`, `CAISSIER`, `TRESORIER`).
8.  **Permission (permission)** : Mapping entre Rôle, Feature, et Action (`READ`, `WRITE`, `ADMIN`).
9.  **RoleAssignment (role_assignment)** : Attribution d'un rôle à un membre dans un contexte spécifique (Eglise, Zone, Association, Sous-groupe) avec des bornes temporelles (`startDate`, `endDate`) et d'activation (`isActive`).

---

## 3. Liste Exhaustive des APIs Consommatrices (REST)

Toutes les APIs sont préfixées par `/api` et supportent les formats `json`, `jsonld` et `jsonhal`.

### A. Église (Fiangonana)
*   `GET /api/fiangonanas` : Récupère la liste de toutes les paroisses.
*   `POST /api/fiangonanas` : Crée une nouvelle paroisse.
    *   *Payload* : `{"nom": "Paroisse de Behoririka", "code": "BEHO"}`
*   `GET /api/fiangonanas/{id}` : Récupère les détails d'une paroisse spécifique.
*   `PATCH /api/fiangonanas/{id}` : Met à jour partiellement une paroisse.
*   `DELETE /api/fiangonanas/{id}` : Supprime une paroisse.

### B. Membres (Membre)
*   `GET /api/membres` : Liste de tous les membres inscrits.
*   `POST /api/membres` : Inscrit un nouveau membre et l'affecte optionnellement à son église et à sa zone géographique (groupe).
    *   *Payload* :
        ```json
        {
          "nom": "Ratsimbazafy",
          "prenom": "Nirina",
          "email": "nirina@example.com",
          "telephone": "+261320000000",
          "dateNaissance": "1990-08-24T00:00:00Z",
          "zoneGeographique": "/api/groupes/2",
          "fiangonana": "/api/fiangonanas/1",
          "associations": [
            "/api/associations/1"
          ]
        }
        ```
*   `GET /api/membres/{id}` : Récupère les détails et l'historique d'un membre.
*   `PATCH /api/membres/{id}` : Modifie les informations d'un membre.
*   `DELETE /api/membres/{id}` : Désinscrit/supprime un membre.

### C. Zones Géographiques (Groupe)
*   `GET /api/groupes` : Liste des zones résidentielles (Groupes).
*   `POST /api/groupes` : Crée un nouveau groupe résidentiel.
    *   *Payload* : `{"nom": "Groupe d'Andohanofotsy", "description": "Zone sud", "fiangonana": "/api/fiangonanas/1"}`
*   `GET /api/groupes/{id}` : Détails d'un groupe.
*   `PATCH /api/groupes/{id}` : Modifie un groupe.
*   `DELETE /api/groupes/{id}` : Supprime un groupe.

### D. Associations (Association)
*   `GET /api/associations` : Liste de toutes les associations de la paroisse.
*   `POST /api/associations` : Crée une association (ex: Chorale, Jeunes).
    *   *Payload* : `{"nom": "Chorale Tanora", "description": "Chorale des jeunes", "fiangonana": "/api/fiangonanas/1"}`
*   `GET /api/associations/{id}` : Détails d'une association.
*   `PATCH /api/associations/{id}` : Modifie une association.
*   `DELETE /api/associations/{id}` : Supprime une association.

### E. Sous-Groupes (SousGroupe)
*   `GET /api/sous_groupes` : Liste des comités ou cellules internes.
*   `POST /api/sous_groupes` : Rattache un sous-groupe à une association parente.
    *   *Payload* : `{"nom": "Comité des Jeunes Adultes", "description": "Cellule d'entraide", "association": "/api/associations/1"}`
*   `GET /api/sous_groupes/{id}` : Détails d'un sous-groupe.
*   `PATCH /api/sous_groupes/{id}` : Modifie un sous-groupe.
*   `DELETE /api/sous_groupes/{id}` : Supprime un sous-groupe.

### F. Rôles & Sécurité (Role, Feature, Permission)
*   `GET /api/roles` | `POST /api/roles` : Gestion des rôles applicatifs (ex: `CAISSIER`).
*   `GET /api/features` | `POST /api/features` : Gestion des fonctionnalités sécurisées (ex: `FINANCES`).
*   `GET /api/permissions` | `POST /api/permissions` : Configuration des matrices de permissions.
    *   *Payload POST `/api/permissions`* :
        ```json
        {
          "role": "/api/roles/1",
          "feature": "/api/features/2",
          "action": "WRITE"
        }
        ```

### G. Attributions de Rôles & Mandats (RoleAssignment)
*   `GET /api/role_assignments` : Historique et liste de tous les mandats.
*   `POST /api/role_assignments` : Élection ou attribution d'un mandat contextuel.
    *   *Payload* :
        ```json
        {
          "membre": "/api/membres/12",
          "role": "/api/roles/3",
          "associationContext": "/api/associations/1",
          "startDate": "2025-01-01T00:00:00Z",
          "endDate": "2025-12-31T23:59:59Z",
          "exerciceYear": "2025",
          "isActive": true
        }
        ```
*   `PATCH /api/role_assignments/{id}` : Archivage ou désactivation d'un mandat en cours (par exemple lors d'une passation).
    *   *Payload* : `{"isActive": false, "endDate": "2024-12-31T23:59:59Z"}`

### H. Carte de Membre, QR Code, Présences/Scans & Statistiques

Chaque membre possède un identifiant de QR code unique (`qrCodeToken`) généré automatiquement sous forme de jeton sécurisé lors de son inscription.

#### 1. Carte de Membre Officielle
*   `GET /api/membres/{id}/carte` : Génère la fiche/carte de membre officielle.
    *   *Rendu HTML par défaut* : Rendu Twig/Tailwind CSS responsive prêt pour impression avec image QR Code base64 encodée.
    *   *Format JSON* : Accessible via `?format=json` ou header `Accept: application/json`. Retourne les informations du membre, les affiliations, l'URL de scan (`/membres/scan/{token}`) et le lien vers les statistiques de participation.

#### 2. Génération de l'image du QR Code
*   `GET /api/membres/{id}/qr-code` : Génère et retourne l'image PNG binaire haute définition (300x300 px) du QR Code unique du membre. Le QR Code encode par défaut l'URL du service de pointage de présence (`/membres/scan/{token}`).
    *   *Format* : Retourne directement un flux d'image binaire avec le header HTTP `Content-Type: image/png`.

#### 3. Enregistrement d'une présence ou participation (scan)
Lorsqu'un membre présente sa carte lors d'un événement, d'une activité de groupe/association ou d'une formation des jeunes, le responsable scanne le QR code (le QR code pointe directement vers `/membres/scan/{token}`) :
*   `GET|POST /membres/scan/{token}` : Portal web interactif de prise de présence par scan.
*   `POST /api/presences` : Endpoint REST pour enregistrer une présence.
    *   *Payload* :
        ```json
        {
          "membre": "/api/membres/12",
          "activityName": "Formation des Jeunes 2026",
          "scannedBy": "/api/membres/1"
        }
        ```
*   `GET /api/presences` : Liste de toutes les présences.

#### 4. Suivi et Taux de Participation Annuel
*   `GET /api/membres/{id}/participation-stats` : Calcule le taux de participation annuel d'un membre (`?year=2026`), fournissant le nombre total d'activités, le nombre d'activités assistées, le pourcentage de participation, le nombre et le taux de retards (`lateCount`, `lateRate`), ainsi que les journaux de présence enrichis de l'état de retard (`isLate`, `delayMinutes`).

#### 5. Gestion Financière : Cotisations Mensuelles & Dons
*   `GET /api/membres/{id}/finances` : Récupère la synthèse financière annuelle du membre (grille des 12 mois x 4 tranches, nombre de mois cotisés sur 12, total cotisations et liste des dons libres).
*   `POST /api/membres/{id}/cotisations/add` : Enregistre un paiement de cotisation pour un mois (mois 1 à 12) et une tranche spécifique (1 à 4).
*   `POST /api/membres/{id}/dons/add` : Enregistre un don libre avec montant et libelle.

---

## 4. Déploiement & Initialisation de la Base MySQL

Pour initialiser la base de données MySQL dans votre environnement :

1.  **Configuration** : Mettez à jour votre fichier `.env` ou créez un fichier `.env.local` contenant vos identifiants MySQL réels :
    ```ini
    DATABASE_URL="mysql://username:password@127.0.0.1:3306/paroisse?serverVersion=8.0.32&charset=utf8mb4"
    ```
2.  **Création de la Base** :
    ```bash
    php bin/console doctrine:database:create
    ```
3.  **Exécution des Migrations** : Appliquez les migrations pré-générées pour créer instantanément l'intégralité du schéma relationnel optimisé :
    ```bash
    php bin/console doctrine:migrations:migrate --no-interaction
    ```
