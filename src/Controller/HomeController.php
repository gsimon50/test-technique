<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{

    ////// Affichage de la home //////

        #[Route('/home', name: 'app_home')]
        public function index(Request $request): Response
        {
            $user = $request->getSession()->get('user');

            if(!$user){
                return $this->redirectToRoute('app_login');
            }

            if(!$user['checkemail']){
                return $this->redirectToRoute('app_mailCheck',[
                    'id' => $user["id"],
                ]);
            }

            return $this->render('home/index.html.twig', [
                'user_name' => $user['nom'] .' '. $user['prenom'],
            ]);
        }

    ////// //////


    ////// Deconnexion //////

        #[Route('/logout', name: 'app_logout')]
        public function logout(Request $request): Response {
            $request->getSession()->clear();
            return $this->redirectToRoute('app_login');
        }
        
    ////// //////

}
