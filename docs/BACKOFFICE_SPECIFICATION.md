# Spécification Technique & Fonctionnelle de l'Administration Backoffice

Ce document décrit l'architecture, les fonctionnalités et la carte des routes du module d'administration backoffice de gestion paroissiale.

---

## 1. Vision & Objectifs

L'administration backoffice (`/admin`) offre un espace de gestion centralisé, moderne et sécurisé réservé aux dirigeants, secrétaires et responsables d'associations. Elle permet de :
* Suivre en temps réel les effectifs et indicateurs clés de la paroisse (`Fiangonana`), des zones géographiques (`Groupe`) et des associations.
* Gérer les fiches de membres, générer et imprimer leurs cartes d'identité officielles.
* Consulter les statistiques d'assiduité, les rapports d'activités et les taux de participation annuels.
* Gérer les mandats et droits d'accès via le modèle de sécurité contextuel (CRBAC).

---

## 2. Architecture Technique & Principes UI

* **Séparation MVC Strict** : Tout le rendu visuel est confiné dans le dossier `templates/admin/` sous forme de templates Twig responsives utilisant Tailwind CSS.
* **Architecture des Controllers** :
  * `AdminDashboardController` (`/admin/dashboard`) : Vue synthétique et tableaux de bord KPI.
  * `AdminMembreController` (`/admin/membres`, `/admin/membres/nouveau`, `/admin/membres/{id}/editer`) : Inscription, modification, filtrage et gestion des cartes/QR codes.
  * `AdminPresenceController` (`/admin/presences`) : Consolidation des événements, historique des scans et rapports de participation.
* **Sécurité CRBAC** : Accès contrôlé par les rôles et habilitations définis dans la matrice `Permission` et `RoleAssignment`.

---

## 3. Cartographie des Routes Backoffice

```mermaid
graph TD
    Root[/admin] --> Dashboard[/admin/dashboard]
    Root --> Membres[/admin/membres]
    Root --> Presences[/admin/presences]
    Root --> Securite[/admin/securite]

    Membres --> MembreNew[/admin/membres/nouveau]
    Membres --> MembreEdit[/admin/membres/{id}/editer]
    Membres --> MembreCarte[/api/membres/{id}/carte]
    Membres --> MembreStats[/api/membres/{id}/participation-stats]
```

---

## 4. Layout & Composants Twig (`templates/admin/`)

1. **`templates/admin/base.html.twig`** : Gabarit principal intégrant :
   * Barre de navigation latérale (Sidebar) responsive avec liens actifs.
   * Barre supérieure (Topbar) affichant l'utilisateur connecté et sa paroisse.
   * Zone de messages flash pour les notifications de succès/erreur.
2. **`templates/admin/dashboard.html.twig`** : Tableau de bord exécutif avec cartes KPI, graphiques de répartition et activité récente.
3. **`templates/admin/membres/index.html.twig`** : Tableau interactif des membres avec barre de recherche, filtres par zone/association et menu d'actions rapides (Carte, Statistiques, Édition).
4. **`templates/admin/membres/form.html.twig`** : Formulaire dynamique d'ajout/édition d'un membre.
5. **`templates/admin/presences/index.html.twig`** : Console de suivi des événements, taux de présence global et journal des scans en direct.
