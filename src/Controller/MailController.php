<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\CodecheckerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\DBAL\Connection;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\MailerInterface;




final class MailController extends AbstractController
{

    public function __construct(
        private Connection $connection,
        private UserRepository $userRepository,
        private CodecheckerRepository $codeCheckerRepository,
        private MailerInterface $mailer,
    ) {}

    ////// Verification du mail  //////
        #[Route('/mailCheck', name: 'app_mailCheck')]
        public function mailVerification(int $id): Response{

            /// Création du code et enregistrement
            $code = rand(100000, 999999);
            $expiredAt = date('Y-m-d H:i:s', strtotime('+20 minutes'));
            $user = $this->userRepository->findUserById($id);

            if(empty($user)){
                return $this->render('mail/index.html.twig', [
                    'controller_name' => 'MailController',
                    'error' => 'pas de compte',
                ]);
            }

            $this->duplicatCode($id);

            $this->connection->executeStatement(
                'INSERT INTO codechecker (id_user, code, enddate) 
                    VALUES (:id_user, :code, :enddate)',
                [
                    'id_user'      => $id,
                    'code'   => $code,
                    'enddate'    => $expiredAt,
                ]
            );

            /// Envoie du code

            $email = (new Email())
            ->from('noreply@test-technique.fr')
            ->to($user->getEmail())
            ->subject('Votre code de validation')
            ->text('Voici le code de validation <br> '.$code.' <br> Bonne reception');
            $this->mailer->send($email);

            /// Post du code -> Si il est ok alors validation ok

            return $this->render('mail/index.html.twig', [
                'controller_name' => 'MailController',
                'error' => null,
            ]);
        }   

    ////// //////

    ////// Check duplicat code  //////

        private function duplicatCode(int $idUser) : bool{

            $code = $this->codeCheckerRepository->findCodeByUserId($idUser);
            if (!empty($code)){
                $this->deleteOldCode($idUser);
            }

            return true;

        }

    ////// //////

    ////// Remove old code //////

        private function deleteOldCode(int $idUser): bool {

            $this->connection->executeStatement(
                'DELETE FROM codechecker WHERE id_user = :id_user',
                [
                    'id_user'      => $idUser,
                ]
            );

            return true;

        }

    ////// //////


    ////// Check code //////

        #[Route('/mailCheck', name: 'app_mailCheck_post', methods: ['POST'])]
        public function checkCode(int $id, Request $request): Response {

            try {

                $code = $this->codeCheckerRepository->findCodeByUserId($id);
                $code_reponse = $request->request->get('code');

                if($code->getCode() != $code_reponse) {
                    throw new \InvalidArgumentException('Les code ne correspondent pas.');
                }            
            } catch (\InvalidArgumentException $e){
                return $this->render('mail/index.html.twig', [
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $this->updateUser($id);
            } catch  (\InvalidArgumentException $e){
                    return $this->render('mail/index.html.twig', [
                    'error' => $e->getMessage(),
                ]);
            }
            return $this->redirectToRoute('app_home');

        }


    ////// //////

    ////// updateUser //////

    private function updateUser(int $idUser): bool {
        $this->connection->executeStatement(
            'UPDATE user SET checkemail = 1 WHERE id = :id',
            ['id' => $idUser]
        );

        return true;
    }


    ////// //////








}
