============================================================
  XFILES — L'Intelligence Collective
  Plateforme de partage de ressources académiques
============================================================

1. PRESENTATION
---------------
XFILES est une plateforme web permettant aux étudiants de partager,
découvrir et améliorer des ressources académiques (cours, TD, TP,
examens, résumés). Le projet utilise PHP/MySQL avec PDO.

2. ARBORESCENCE
---------------
mini/
├── config.php              Configuration BDD + connexion PDO + CSRF
├── config.infinityfree.php  Configuration hébergement InfinityFree
├── index.php                Page d'accueil publique
├── login.php                Page de connexion
├── register.php             Page d'inscription
├── logout.php               Déconnexion
├── admin_approve.php        Interface d'approbation des fichiers refusés
├── dashboard.php            Dashboard principal (explorateur + profil)
├── upload.php               Upload de documents
├── admin.php                Administration (réservé aux admins)
├── download.php             Téléchargement de fichiers (BLOB)
├── view.php                 Visualisation inline de fichiers
├── preview.php              API JSON de preview pour les modals
├── projet.sql               Base de données complète avec données test
├── .htaccess                Configuration Apache
├── includes/
│   ├── auth.php             Authentification + RBAC
│   ├── functions.php        Fonctions utilitaires partagées
│   ├── MailService.php      Classe PHPMailer pour envoi SMTP
│   ├── mailer.php           Fonctions d'envoi d'email
│   ├── header.php           Header HTML partagé
│   ├── footer.php           Footer HTML partagé
│   ├── navbar.php           Navbar publique partagée
│   ├── sidebar.php          Sidebar dashboard partagée
│   ├── upload_security.php  Validation et sécurité des uploads
│   ├── config.php           (ancien, conservé pour compatibilité)
│   └── db.php               (ancien, conservé pour compatibilité)
├── config/
│   └── mail.php             Configuration SMTP (Gmail)
├── templates/
│   └── emails/
│       ├── password_reset.php    Template email reset password
│       └── upload_rejected.php     Template email upload refusé
├── vendor/                  Dépendances Composer (PHPMailer)
├── assets/
│   ├── css/
│   │   ├── style.css        Variables CSS globales
│   │   ├── index.css        Styles page d'accueil
│   │   ├── dashboard.css    Styles dashboard
│   │   ├── responsive.css   Media queries responsive
│   │   └── components/      Styles composants (auth, buttons, etc.)
│   ├── js/
│   │   ├── index.js         JS page d'accueil (thème, menu)
│   │   └── auth.js          JS authentification (toggle password)
│   └── img/                 Images et illustrations
├── scripts/
│   └── analyze_image.py     Script Python NudeNet (modération images)
└── logs/                    Logs de sécurité upload

3. INSTALLATION
---------------
a) Importer la base de données :
   mysql -u root -p < projet.sql

b) Configurer la connexion dans config.php :
   - Local : DB_HOST = 127.0.0.1:3307, DB_NAME = xfiles
   - InfinityFree : config.infinityfree.php est chargé automatiquement

c) Placer le projet dans le répertoire web du serveur

4. COMPTE ADMIN
---------------
Login    : ENSIASD
Password : ENSIASD2026
Role     : admin

5. AUTHENTIFICATION PAR LOGIN
-----------------------------
L'authentification se fait uniquement par LOGIN (pas d'email).
- Login : identifiant unique choisi par l'utilisateur (3-50 caractères, lettres/chiffres/_)
- Mot de passe : minimum 8 caractères

Inscription : name + login + password + filière
Connexion   : login + password

6. MOT DE PASSE OUBLIÉ
-----------------------
La réinitialisation de mot de passe se fait uniquement par l'administrateur :
- L'utilisateur contacte l'administrateur directement
- L'admin se connecte au dashboard > onglet Utilisateurs > bouton 🔑 pour reset
- Le nouvel utilisateur reçoit son mot de passe temporaire

7. UPLOADS EN ATTENTE (DASHBOARD ADMIN)
----------------------------------------
Si un fichier est refusé par le système de sécurité :
1. Le fichier est stocké dans la base avec status 'en_attente'
2. L'admin voit une notification dans le dashboard (badge "En attente")
3. Onglet "En attente" : liste des documents à valider
4. Actions disponibles : Approuver ✅ ou Rejeter ❌
5. L'admin peut voir la raison du refus et télécharger le fichier

Colonnes ajoutées à la table documents :
- rejection_reason TEXT (raison du refus initial)

8. SECURITE
-----------
- PDO prepared statements sur toutes les requêtes SQL
- password_hash / password_verify pour les mots de passe
- CSRF token sur tous les formulaires
- Validation MIME type réelle (finfo) sur les uploads
- Extensions exécutables interdites
- Détection de contenu inapproprié (NudeNet)
- session_regenerate_id après connexion
- Destruction propre des cookies de session

9. ENVIRONNEMENTS
-----------------
- Local    : 127.0.0.1:3307, BASE_URL=/mini/
- InfinityFree : sql100.infinityfree.com, BASE_URL=/
- Render   : Variables d'environnement DB_HOST, DB_NAME, etc.

10. FICHIERS OBSOLÈTES (Email supprimé)
--------------------------------------
Les fichiers suivants sont conservés mais inutilisés (fonctionnalité email supprimée) :
- includes/mailer.php (obsolète)
- includes/MailService.php (obsolète)
- config/mail.php (obsolète)
- admin_approve.php (obsolète - redirection vers admin.php)
- templates/emails/ (obsolète)

Toutes les notifications sont maintenant gérées via le dashboard admin.

11. TECHNOLOGIES
---------------
- PHP 8+ (PDO, password_hash, sessions)
- MySQL 8+
- Font Awesome 6.4
- jQuery + Nice Select
- CSS Custom Properties (thème clair/sombre)

12. NETTOYAGE DE CODE (2026-04-26)
-----------------------------------
Fichiers marqués comme supprimés :
- includes/mailer.php, MailService.php (email obsolète)
- config/mail.php (config SMTP obsolète)
- templates/emails/* (templates email obsolètes)
- admin_approve.php (fonctionnalité token supprimée)
- test.html, assets/css/index2.html (fichiers tests)
- infinityfree.sql, projet.sql, migrate_add_rejection_reason.sql (redondants)

Code nettoyé :
- includes/functions.php : suppression isValidEmail() (non utilisé)
- includes/auth.php : suppression hasRole() et requireRole() (non utilisés)

Note : Les fichiers sont conservés physiquement mais vidés/marqués comme supprimés
pour éviter les erreurs d'inclusion éventuelles dans des fichiers non audités.
