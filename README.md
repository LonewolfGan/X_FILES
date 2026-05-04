# XFILES — L'Intelligence Collective

Plateforme de partage de ressources académiques pour étudiants : cours, TD, TP, annales d'examens et résumés.

## Technologies

- **Backend** : PHP 8.2 + PDO (MariaDB/MySQL)
- **Frontend** : CSS personnalisé, Font Awesome 6, jQuery + Nice Select
- **Serveur** : PHP built-in server (port 5000) + MariaDB via socket Unix
- **Hébergement** : Replit (dev) / Render / InfinityFree (prod)

## Structure

```
├── config.php              Configuration BDD + PDO + CSRF
├── index.php               Page d'accueil publique
├── router.php              Routeur PHP built-in server
├── start.sh                Script de démarrage MariaDB + PHP
├── projet.sql              Schéma + données initiales
├── pages/
│   ├── login.php           Connexion
│   ├── register.php        Inscription
│   ├── dashboard.php       Explorateur de ressources + profil
│   ├── upload.php          Upload de documents
│   ├── admin.php           Panneau d'administration
│   ├── download.php        Téléchargement de fichiers
│   ├── view.php            Visualisation inline
│   └── logout.php          Déconnexion
├── includes/
│   ├── auth.php            Authentification + RBAC
│   ├── functions.php       Fonctions utilitaires partagées
│   ├── upload_security.php Validation et sécurité des uploads
│   ├── header.php          Header HTML partagé
│   ├── navbar.php          Navbar publique
│   └── sidebar.php         Sidebar dashboard
├── css/                    Feuilles de style modulaires
├── js/                     Scripts client
└── images/                 Illustrations
```

## Lancer le projet (Replit)

Le projet démarre automatiquement via le bouton **Run**. Le script `start.sh` :
1. Initialise et démarre MariaDB via socket Unix
2. Crée la base `xfiles` et importe `projet.sql` si nécessaire
3. Lance le serveur PHP sur `0.0.0.0:5000`

## Compte admin par défaut

| Champ    | Valeur       |
|----------|-------------|
| Login    | `ENSIASD`   |
| Password | `ENSIASD2026` |
| Rôle     | `admin`     |

## Authentification

- Login unique (lettres, chiffres, `_`) — pas d'email
- Mot de passe minimum 8 caractères
- Réinitialisation du mot de passe par l'administrateur uniquement (dashboard admin > Utilisateurs)

## Sécurité

- PDO prepared statements sur toutes les requêtes SQL
- `password_hash` / `password_verify` pour les mots de passe
- Jeton CSRF sur tous les formulaires
- Validation MIME type réelle (`finfo`) sur les uploads
- Extensions exécutables interdites
- `session_regenerate_id` après connexion
- Modération d'images via SightEngine API (optionnel)

## Types de ressources

`cours` · `td` · `tp` · `examen` · `resume`

## Environnements

| Env         | DB Host              | BASE_URL   |
|-------------|----------------------|------------|
| Local       | `127.0.0.1:3307`     | `/`        |
| InfinityFree| `config.infinityfree.php` | `/`   |
