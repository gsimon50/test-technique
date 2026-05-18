<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Service\CheckPasswordService;



final class AuthController extends AbstractController
{

    public function __construct(
        private Connection $connection,
        private RequestStack $requestStack,
        private CheckPasswordService $checkPassword,


    ) {}
    
    ////// Accès a la page de connexion //////
        
        #[Route('/login', name: 'app_login')]
        public function index(Request $request): Response
        {
            $info = $request->query->get('info');

            return $this->render('auth/index.html.twig', [
                'login' => true,
                'mail' => null,
                'error' => '',
                'info' => $info,
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
                    'info' => null,

                ]);
            }

            $data = $this->getUserInfo($mail);

            $this->setSession($data);

            if(!$data->checkemail){
                return $this->redirectToRoute('app_mailCheck',[
                    'id' => $data->userId,
                ]);
            }

            return $this->redirectToRoute('app_home');
        }


    ////// //////

    ////// Accès a la page de creation //////

        #[Route('/register', name: 'app_register', methods: ['GET'])]
        public function register(): Response {
            return $this->render('auth/index.html.twig', [
                'login' => false,
                'nom' => null,
                'prenom' => null,
                'mail' => null,
                'mail_check' => null,
                'birthdate'        => null,
                'social_security'  => null,
                'fighter_name'     => null,
                'numcombat'        => null,
                'pokemon'          => null,
                'error' => '',
                'info' => null,

            ]);
        }

    ////// //////

    ////// Création //////

        #[Route('/register', name: 'app_register_submit', methods: ['POST'])]
        public function createUser(Request $request): Response {
            $data = (object) [
                'nom'              => $request->request->get('nom'),
                'prenom'           => $request->request->get('prenom'),
                'email'            => $request->request->get('email'),
                'password'         => $request->request->get('password'),
                'birthdate'        => $request->request->get('birthdate'),
                'social_security'  => $request->request->get('social_security'),
                'fighter_name'     => $request->request->get('fighter_name'),
                'numcombat'        => $request->request->get('numcombat'),
                'pokemon'          => $request->request->get('pokemon'),
            ];

            $email_confirm = $request->request->get('email_confirm');
            $password_confirm = $request->request->get('password_confirm');
   
            try {
                $data->email    = $this->checkmail($data->email, $email_confirm);
                $data->password = $this->checkPassword->checkpsw($data->password, $password_confirm);
                $data->fighter_name = $this->checkfightername($data->fighter_name);
            } catch (\InvalidArgumentException $e) {
                return $this->render('auth/index.html.twig', [
                    'login' => false,
                    'nom' => $data->nom,
                    'prenom' => $data->prenom,
                    'mail' => $data->email,
                    'mail_check' => $email_confirm,
                    'birthdate' => $data->birthdate,
                    'social_security' => $data->social_security,
                    'fighter_name' => $data->fighter_name,
                    'pokemon' => $data->pokemon,
                    'error' => $e->getMessage(),
                    'info' => null,

                ]);
            }


            
            $this->connection->executeStatement(
                'INSERT INTO user (nom, prenom, email, password,birthday,numsecu,pseudo,numcombat,pokemon,roles) 
                    VALUES (:nom, :prenom, :email, :password,:birthday, :numsecu ,:pseudo ,:numcombat ,:pokemon ,:roles)',
                [
                    'nom'      => $data->nom,
                    'prenom'   => $data->prenom,
                    'email'    => $data->email,
                    'password' => $data->password,
                    'birthday' => $data->birthdate,
                    'numsecu' => $data->social_security,
                    'pseudo' => $data->fighter_name,
                    'numcombat' => 1,
                    'pokemon' => $data->pokemon,
                    'roles'    => '[]',
                ]
            );

            $data->userId = $this->connection->lastInsertId();

            $this->getNumeroAccreditation($data->userId);
            $data->checkemail = 0;

            $this->setSession($data);
            
            return $this->redirectToRoute('app_mailCheck',[
                'id' => $data->userId,
            ]);
        }
    ////// //////

    ////// checkConnexion //////

        private function checkConnexion(string $mail,string $psw): bool {

            if(!$this->getEmailPassword($mail, $psw)){
                throw new \InvalidArgumentException('Le mail ou le mot de passe ne correspond pas.');
            }

            return true;
        }


    ////// //////

    ////// Verification du mot de passe //////

        private function getEmailPassword(string $mail,string $psw): bool {
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

    ////// Vérification du mail et si l'utilisateur n'existe pas déjà //////
        private function checkmail(string $mail,string $mail_check): string {
            if ($mail !== $mail_check) {
                throw new \InvalidArgumentException('Les adresses e-mail ne correspondent pas.');
            }

            if ($this->checkEmailAllreadyExist($mail)) {
                throw new \InvalidArgumentException('Email déjà existant.');
            }

            return $mail;
        }
    ////// //////

    ////// check Email si il exite déjà en bdd //////
        private function checkEmailAllreadyExist(string $mail): ?bool {
            $result = $this->connection->executeQuery(
                'SELECT * FROM user WHERE email = :email',
                ['email' => $mail]
            )->fetchOne();

            return $result;
        }
    ////// //////

     ////// Vérification du mail et si l'utilisateur n'existe pas déjà //////
        private function checkfightername(string $pseudo): string {

            $result = $this->connection->executeQuery(
                    'SELECT * FROM user WHERE pseudo = :pseudo',
                    ['pseudo' => $pseudo]
            )->fetchOne();

            if ($result) {
                throw new \InvalidArgumentException('Pseudo déjà existant.');
            }

            return $pseudo;
        }
    ////// //////

    ////// Get info User //////

        private function getUserInfo(string $mail): ?object {
            $result = $this->connection->executeQuery(
                'SELECT id as userId, email, checkemail, nom, prenom FROM user WHERE email = :email;',
                ['email' => $mail]
            )->fetchAssociative();

            return $result ? (object) $result : null;
        }

    ////// //////

    ////// GetNuméro accreditation //////

    private function getNumeroAccreditation(int $id): bool
    {
        $num_cerfa = 666 . $id;

        $this->connection->executeStatement(
            'UPDATE user SET numcombat = :numcombat WHERE id = :id',
            [
                'numcombat' => $num_cerfa,
                'id' => $id
            ]
        );

        return true;
    }

    ////// //////

    ////// setSession //////

        private function setSession(object $data): bool {

            $session = $this->requestStack->getSession();

            $session->set('user', [
                'id'     => $data->userId,
                'nom'    => $data->nom,
                'prenom' => $data->prenom,
                'email'  => $data->email,
                'birthday' => $data->birthdate,
                'numsecu' => $data->social_security,
                'pseudo' => $data->fighter_name,
                'numcombat' => $data->numcombat,
                'pokemon' => $data->pokemon,
                'checkemail'  => ($data->checkemail)? 1 : 0,
            ]);

            return true;

        }
    ////// //////

}
