<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\CodecheckerRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\DBAL\Connection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Service\CheckPasswordService;
use App\Service\MailTransportService;

final class MailController extends AbstractController
{

    public function __construct(
        private Connection $connection,
        private UserRepository $userRepository,
        private CodecheckerRepository $codeCheckerRepository,
        private CheckPasswordService $checkPassword,
        private MailTransportService $mail_transport_service,

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
                    'error' => 'Pas de compte relier a cette email.',
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

            $from = "noreply@test-technique.fr";
            $to = $user->getEmail();
            $subject = "Votre code de validation";
            $text =  'Voici le code de validation <br> '.$code.' <br> Bonne reception';

            $email = $this->mail_transport_service->mailer($from,$to,$subject,$text);

            if (!$email) {
                return $this->render('mail/index.html.twig', [
                    'controller_name' => 'MailController',
                    'error' => 'Problème de mail, merci de réessayer plus tard.',
                ]);
            } else {
                 return $this->render('mail/index.html.twig', [
                    'error' => 'Vérifier votre boite mail afin de valider le code.',
                ]);
            }
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

            $user = $request->getSession()->get('user');
            $user['checkemail'] = '1';
            $request->getSession()->set('user', $user);
            
            return $this->redirectToRoute('app_home');

        }


    ////// //////

    ////// passwordReset //////

        #[Route('/resetPassword', name: 'app_resetPassword')]
        public function resetPassword(Request $request): Response{
            if ($request->isMethod('POST')) {
                $email = $request->request->get('email');

                if($email == null){
                    return $this->render('mail/reset.html.twig', [
                        'error' => "Pas d'adresse mail renseigner",
                    ]);     
                }
                $user = $this->userRepository->findUserByEmail($email);

                try {
                    if(!$user){
                        throw new \InvalidArgumentException('Aucun compte n\'est relier a cette adresse email.');
                    }
                } catch (\InvalidArgumentException $e){
                    return $this->render('mail/reset.html.twig', [
                        'error' => $e->getMessage(),
                    ]);            
                }

                $email_reset = $this->resetPasswordEmail($user);

                 if (!$email_reset) {
                    return $this->render('mail/reset.html.twig', [
                        'controller_name' => 'MailController',
                        'error' => 'Problème de mail, merci de réessayer plus tard.',
                    ]);
                } else {
                    return $this->redirectToRoute('app_login',[
                        'info' => "Un mail de reinitiliation vient de vous être envoyé",
                    ]);
                }
            }

            return $this->render('mail/reset.html.twig', [
                'controller_name' => 'MailController',
                'error' => null,
            ]);
        }

    ////// //////

    ////// newPassword //////

        #[Route('/newpassword/{token}', name: 'app_newPassword')]
        public function newPassword(Request $request, string $token){

            $key = $_ENV['KEYRESETPASS'];

            $data = base64_decode(strtr($token, '-_', '+/') . '==');

            $iv = substr($data, 0, 16);
            $encrypted = substr($data, 16);

            $id = openssl_decrypt(
                $encrypted,
                'AES-256-CBC',
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );

            if ($request->isMethod('POST')) {
                
                $password = $request->request->get('password');
                $password_confirm = $request->request->get('password');

                try {
                    $password = $this->checkPassword->checkpsw($password, $password_confirm);
                } catch (\InvalidArgumentException $e) {
                    return $this->render('mail/newpsw.html.twig', [
                        'error' => $e->getMessage(),
                    ]);
                }

                $this->connection->executeStatement(
                    'UPDATE user SET password = :password WHERE id = :id',
                    [
                        'password' => $password,
                        'id'       => $id,
                    ]
                );

                return $this->redirectToRoute('app_login',[
                    "info" => "Votre mot de passe c'est bien reinitialiser"
                ]);
            }

            
            return $this->render('mail/newpsw.html.twig', [
                'error' => null,
            ]);
        }

    ////// //////

    ////// Check duplicat code verif email //////

        private function duplicatCode(int $idUser) : bool{

            $code = $this->codeCheckerRepository->findCodeByUserId($idUser);
            if (!empty($code)){
                $this->deleteOldCode($idUser);
            }

            return true;

        }

    ////// //////

    ////// Remove old code email//////

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

    ////// update User on checkemail //////

        private function updateUser(int $idUser): bool {
            $this->connection->executeStatement(
                'UPDATE user SET checkemail = 1 WHERE id = :id',
                ['id' => $idUser]
            );

            return true;
        }

    ////// //////

    ////// resetPasswordEmail //////

        private function resetPasswordEmail(object $user): bool{

                $key = $_ENV['KEYRESETPASS'];
                $iv = random_bytes(16);

                $encrypted = openssl_encrypt(
                    $user->getId(),
                    'AES-256-CBC',
                    $key,
                    OPENSSL_RAW_DATA,
                    $iv
                );

                $token  = rtrim(strtr(base64_encode($iv . $encrypted), '+/', '-_'), '=');


            $url = $this->generateUrl('app_newPassword', 
                ['token' => $token],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $from = "noreply@test-technique.fr";
            $to = $user->getEmail();
            $subject = "Votre lien de réinitialisation de mot de passe";
            $text =  'Voici le liens pour reinitialiser votre mot de passe :'.$url;

            $email = $this->mail_transport_service->mailer($from,$to,$subject,$text);

            return $email;

        }
    
    ////// //////
}
