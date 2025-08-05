<?php

namespace App\Controller;

use App\Form\UserType;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/user')]
final class UserController extends AbstractController
{
    #[Route('/profile', name: 'user_profile')]
    public function index(EntityManagerInterface $em,Request $Request): Response
    {
        $user = $this->getUser();

        // Vérifie que l'utilisateur est connecté
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($Request);
        $uploadDir = $this->getParameter('image_directory') . '/photoProfile/';

        if ($form->isSubmitted() == true && $form->isValid()) {

            $image = $form->get('imageFile')->getData();
            if ($image !== null) {
                // Récupérer l'ancien nom de fichier
                $oldFile = $user->getProfilePicture();

                // Supprimer l'ancien fichier s'il existe
                if ($oldFile && file_exists($uploadDir . '/' . $oldFile)) {
                    unlink($uploadDir . '/' . $oldFile);
                }

                // Enregistrer la nouvelle image
                $fileName = uniqid() . '.' . $image->guessExtension();
                $image->move($uploadDir, $fileName);
                $user->setProfilePicture($fileName);
            }
            
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Votre profil a été mis à jour avec succès !');
            return $this->redirectToRoute('user_profile');
        }
        return $this->render('user/index.html.twig', [
            'form' => $form->createView(),
            'user' => $user,  
            'image_directory' => $uploadDir,      
    ]);
    }

}
