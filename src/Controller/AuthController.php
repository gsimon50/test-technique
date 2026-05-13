<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\DBAL\Connection;


final class AuthController extends AbstractController
{

    public function __construct(
        private Connection $connection
    ) {}
    
    ////// Accès a la page de connexion //////
        
        #[Route('/login', name: 'app_login')]
        public function index(): Response
        {
            return $this->render('auth/index.html.twig', [
                'login' => true,
                'mail' => null,
                'error' => '',
            ]);
        }

    ////// //////

    ////// Connexion //////

        #[Route('/login', name: 'app_login_submit', methods: ['POST'])]
        public function connectUser(Request $request): Response {
            $mail   = $request->request->get('email');
            $psw    = $request->request->get('password');

            try {
                $this->checkConnexion($mail, $psw);
            } catch (\InvalidArgumentException $e){
                return $this->render('auth/index.html.twig', [
                    'login' => true,
                    'mail' => $mail,
                    'error' => $e->getMessage(),
                ]);
            }

            return $this->redirectToRoute('app_home');
        }


    ////// //////

    ////// checkUser //////

        private function checkConnexion(string $mail,string $psw){

            if(!$this->getEmailPassword($mail, $psw)){
                throw new \InvalidArgumentException('Le mail ou le mot de passe ne correspond pas.');
            }
        }


    ////// //////

    ////// getEmailPassword //////

        private function getEmailPassword(string $mail,string $psw){
            $result = $this->connection->executeQuery(
                'SELECT * FROM user WHERE email = :email;',
                ['email' => $mail]
            )->fetchAssociative();

            if (!$result) {
                return false;
            }

            return password_verify($psw, $result['password']);
        }


    ////// //////

    ////// Accès a la page de registration //////
        #[Route('/register', name: 'app_register', methods: ['GET'])]
        public function register(): Response
        {
            return $this->render('auth/index.html.twig', [
                'login' => false,
                'nom' => null,
                'prenom' => null,
                'mail' => null,
                'mail_check' => null,
                'error' => '',
            ]);
        }
    ////// //////

    ////// Enregistrement //////

        #[Route('/register', name: 'app_register_submit', methods: ['POST'])]
        public function createUser(Request $request): Response {
            $nom        = $request->request->get('nom');
            $prenom     = $request->request->get('prenom');
            $mail       = $request->request->get('email');
            $mail_check = $request->request->get('email_confirm');
            $psw        = $request->request->get('password');
            $psw_check  = $request->request->get('password_confirm');

            try {
                $password   = $this->checkpsw($psw, $psw_check);
                $email      = $this->checkmail($mail, $mail_check);
            } catch (\InvalidArgumentException $e) {
                return $this->render('auth/index.html.twig', [
                    'login' => false,
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'mail' => $mail,
                    'mail_check' => $mail_check,
                    'error' => $e->getMessage(),
                ]);
            }
            
            $this->connection->executeStatement(
                'INSERT INTO user (nom, prenom, email, password, roles) 
                    VALUES (:nom, :prenom, :email, :password, :roles)',
                [
                    'nom'      => $nom,
                    'prenom'   => $prenom,
                    'email'    => $email,
                    'password' => $password,
                    'roles'    => '[]',
                ]
            );

            // To do //
            // Prevoir un envoie de mail avec vérification mail //

            return $this->redirectToRoute('app_home');
        }
    ////// //////

    ////// Vérification du mot de passe //////
        private function checkpsw(string $psw,string $psw_check): string
        {
            if ($psw !== $psw_check) {
                throw new \InvalidArgumentException('Les mots de passe ne correspondent pas.');
            }

            if (!preg_match('/^(?=.*[A-Z])(?=.*[0-9])(?=.*[^a-zA-Z0-9]).*$/', $psw)) {
                throw new \InvalidArgumentException('Le mot de passe doit contenir au moins une majuscule, un chiffre et un caractère spécial.');
            }

            return password_hash($psw , PASSWORD_BCRYPT);
        }
    ////// //////

    ////// Vérification du mail //////
        private function checkmail(string $mail,string $mail_check): string
        {
            if ($mail !== $mail_check) {
                throw new \InvalidArgumentException('Les adresses e-mail ne correspondent pas.');
            }

            if ($this->getEmail($mail)) {
                throw new \InvalidArgumentException('Email déjà existant.');
            }

            return $mail;
        }
    ////// //////

    ////// Get Email //////
        private function getEmail(string $mail){
            $result = $this->connection->executeQuery(
                'SELECT * FROM user WHERE email = :email',
                ['email' => $mail]
            )->fetchAssociative();

            return $result;
        }
    ////// //////

}
