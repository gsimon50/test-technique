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
    
    #[Route('/login', name: 'app_auth')]
    public function index(): Response
    {
        return $this->render('auth/index.html.twig', [
            'login' => true,
            'error' => '',

        ]);
    }


    ////// Accès a la page de registration //////
        #[Route('/register', name: 'app_register', methods: ['GET'])]
        public function register(): Response
        {
            return $this->render('auth/index.html.twig', [
                'login' => false,
                'error' => '',
            ]);
        }
    ////// //////

    ////// Enregistrement //////

        #[Route('/register', name: 'app_register_submit', methods: ['POST'])]
        public function createUser(Request $request): Response {
            $nom      = $request->request->get('nom');
            $prenom   = $request->request->get('prenom');
            $mail    = $request->request->get('email');
            $mail_check    = $request->request->get('email_confirm');
            $psw = $request->request->get('password');
            $psw_check = $request->request->get('password_confirm');

            try {
                $password = $this->checkpsw($psw, $psw_check);
                $email = $this->checkmail($mail, $mail_check);
            } catch (\InvalidArgumentException $e) {
                return $this->render('auth/index.html.twig', [
                    'login' => false,
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

            $result = $this->connection->executeQuery(
                'SELECT * FROM user WHERE email = :email',
                ['email' => $mail]
            )->fetchAssociative();

            if ($result) {
                throw new \InvalidArgumentException('Email déjà existant.');
            }

            return $mail;
        }
    ////// //////
}
