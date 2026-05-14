TEST-TECHNIQUE
Projet Symfony avec authentification, vérification d'email et réinitialisation de mot de passe.

Prérequis

   PHP 8.4
   Composer
   MAMP (MySQL)
   Symfony CLI

Installation

   1. Cloner le projet
      git clone <url-du-repo>
      cd test-technique

   2. Installer les dépendances PHP
      composer install

   3. Configurer l'environnement

      Modifie .env.dev :

      Base de données
      DATABASE_URL="mysql://root:root@127.0.0.1:3306/test-technique?serverVersion=8.0"

      # Mailer (Mailtrap en dev)
      MAILER_DSN=smtp://username:password@sandbox.smtp.mailtrap.io:2525

      # Clé de chiffrement pour le reset de mot de passe
      KEYRESETPASS=ta_clé_secrète

   4. Démarrer MAMP

      Lance MAMP
      Vérifie que Apache et MySQL sont bien démarrés (voyants verts)
      Vérifie le port MySQL dans MAMP → Préférences → Ports

   5. Créer la base de données

      symfony console doctrine:database:create
      symfony console doctrine:migrations:migrate

   6. Lancer le serveur

      symfony server:start
      L'application est disponible sur http://127.0.0.1:8000

      Routes disponibles
      /login : Page de connexion
      /register : Page d'inscription 
      /home : Page de bienvenue (connecté)
      /logout : Déconnexion
      /mailCheck/{id} : Vérification de l'email
      /newpassword/{token} : Réinitialisation du mot de passe

Stack technique

   Symfony 6.x
   Doctrine DBAL (SQL brut)
   Symfony Mailer
   Symfony UX (Twig Components, Turbo)
   MAMP / MySQL