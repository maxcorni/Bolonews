<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Commentaire;
use App\Form\SearchType;
use App\Form\ArticleType;
use App\Form\CommentType;
use App\Repository\ArticleRepository;
use App\Repository\CategorieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


#[Route("/article")]
final class ArticleController extends AbstractController
{
    #[Route('', name: 'article_index')]
    public function index(ArticleRepository $ArticleRepository): Response
    {
        // Role : Affiche l'article à la une et les 4 derniers articles publiés
        // Article à la une = le plus liké dans les dernières 24h sinon dans la semaine ou de tous les temps
        // latestArticles = 4 derniers articles publiés
        // Retourne l'article à la une et les 4 derniers articles publiés

        $articleUne = $ArticleRepository->findMostLikedInLast24HoursOrWeekOrAllTime();

        $latestArticles = $ArticleRepository->findBy(
            ['publie' => true],
            ['date_creation' => 'DESC'],
            4
        );

        return $this->render('article/index.html.twig', [
            'article_une' => $articleUne,
            'articles' => $latestArticles,
        ]);
    }

    #[Route('/list', name: 'article_list')]
    public function list(ArticleRepository $ArticleRepository, Request $request, CategorieRepository $CategorieRepository): Response {

        // Role : Affiche un formulaire de recherche une liste de filtre de catégorie et la liste des articles correspondants
        // Recherche par mot-clé dans le titre, chapeau ou contenu
        // Affichage des articles publiés correspondants aux critères de recherche
        // Retourne la liste des objets articles publiés ainsi que les catégories disponibles et le formulaire de recherche 
        // En plus si recherche efféctuée les filtres de catégorie et de recherche.


        $searchData = '';
        $form = $this->createForm(SearchType::class);
        $form->handleRequest($request);

        $categoryId = $request->query->get('category');

        if ($form->isSubmitted() && $form->isValid()) {
            $searchData = $form->get('q')->getData();
        }

        if (!empty($searchData) && $categoryId) {
            $articles = $ArticleRepository->findBySearchAndCategory($searchData, $categoryId);
        } elseif (!empty($searchData)) {
            $articles = $ArticleRepository->findBySearch($searchData);
        } elseif ($categoryId) {
            $articles = $ArticleRepository->findBy(['categorie' => $categoryId, 'publie' => true]);
        } else {
            $articles = $ArticleRepository->findBy(['publie' => true], ['date_creation' => 'DESC']);
        }

        $categories = $CategorieRepository->findAll();

        if (empty($articles)) {
            $this->addFlash('warning', 'Aucun article correspondant à la recherche trouvé.');
        } else {
            $this->addFlash('success', 'Articles correspondant à la recherche récupérés avec succès: ' . count($articles));
        }

        return $this->render('article/list.html.twig', [
            'articles' => $articles,
            'categories' => $categories,
            'form' => $form->createView(),
            'currentCategory' => $categoryId,
            'currentSearch' => $searchData
        ]);
    }

    #[Route('/user-list', name: 'article_user_list')]
    public function userList(ArticleRepository $ArticleRepository, Request $request, CategorieRepository $CategorieRepository): Response
    {
        // Role : Affiche un formulaire de recherche une liste de filtre de catégorie et la liste des articles correspondants ainsi que ceux de l'utilisateur connecté
        // Recherche par mot-clé dans le titre, chapeau ou contenu
        // Affichage des articles publiés et non publié de l'utilisateur correspondants aux critères de recherche
        // Retourne la liste des objets articles publiés et non publiés de l'utilisateur connecté ainsi que les catégories disponibles et le formulaire de recherche 
        // En plus si recherche efféctuée les filtres de catégorie et de recherche.

        $searchData = '';
        $form = $this->createForm(SearchType::class);
        $form->handleRequest($request);

        $categoryId = $request->query->get('category');
        $currentUser = $this->getUser();

        if ($form->isSubmitted() && $form->isValid()) {
            $searchData = $form->get('q')->getData();
        }

        $baseFilters = ['auteur' => $currentUser];

        if (!empty($searchData) && $categoryId) {
            $allUserArticles = $ArticleRepository->findBySearchAndCategoryAndAuthor($searchData, $categoryId, $currentUser);
        } elseif (!empty($searchData)) {
            $allUserArticles = $ArticleRepository->findBySearchAndAuthor($searchData, $currentUser);
        } elseif ($categoryId) {
            $allUserArticles = $ArticleRepository->findBy(array_merge($baseFilters, ['categorie' => $categoryId]));
        } else {
            $allUserArticles = $ArticleRepository->findBy($baseFilters, ['date_creation' => 'DESC']);
        }

        $publishedArticles = array_filter($allUserArticles, fn($article) => $article->isPublie());
        $unpublishedArticles = array_filter($allUserArticles, fn($article) => !$article->isPublie());

        if (!empty($publishedArticles) && !empty($unpublishedArticles)) {
            $this->addFlash('success', 'Articles publiés et non publiés récupérés avec succès: ' . count($publishedArticles) . ' publiés, ' . count($unpublishedArticles) . ' non publiés');
        } elseif (empty($unpublishedArticles) && !empty($publishedArticles)) {
            $this->addFlash('success', 'Articles publiés récupérés avec succès: ' . count($publishedArticles));
        } elseif (empty($publishedArticles) && !empty($unpublishedArticles)) {
            $this->addFlash('success', 'Articles non publiés récupérés avec succès:' . count($unpublishedArticles));
        } else {
            $this->addFlash('warning', 'Aucun article trouvé.');
        }   
                    
        $categories = $CategorieRepository->findAll();
        

        return $this->render('article/user_list.html.twig', [
            'categories' => $categories,
            'publishedArticles' => $publishedArticles,
            'unpublishedArticles' => $unpublishedArticles,
            'form' => $form->createView(),
            'currentCategory' => $categoryId,
            'currentSearch' => $searchData
        ]);
    }

    #[Route('/user-show/{id}', name: 'article_user_show')]
    public function userShow(int $id, ArticleRepository $ArticleRepository, Request $request, EntityManagerInterface $em): Response
    {

        // Role : Affiche le detail d'un article de l'utilisateur connecté
        // Affichage le detail de l'article, le formulaire de commentaire et la liste des commentaires, le nombre de like
        // Retourne l'objet article ainsi que le formulaire de commentaire


        $article = $ArticleRepository->find($id);

        if (!$article) {
            $this->addFlash('error', 'Article introuvable.');
            throw $this->createNotFoundException('Article not found');
        }

        if ($article->getAuteur() !== $this->getUser()) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à consulter cet article.');
            $this->redirectToRoute('article_index');
        }

        $comment = new Commentaire();
        $commentForm = $this->createForm(CommentType::class, $comment);
        $commentForm->handleRequest($request);

        if ($commentForm->isSubmitted() && $commentForm->isValid()) {
            $comment->setArticle($article);
            $comment->setAuteur($this->getUser());
            $comment->setDatePublication(new \DateTime());

            $em->persist($comment);
            $em->flush();

            $this->addFlash('success', 'Commentaire ajouté avec succès !');
            return $this->redirectToRoute('article_user_show', ['id' => $id]);
        } elseif ($commentForm->isSubmitted() && !$commentForm->isValid()) {
            $this->addFlash('error', 'Erreur lors de l\'ajout du commentaire. Veuillez réessayer.');
        }

        return $this->render('article/show.html.twig', [
            'article' => $article,
            'commentForm' => $commentForm->createView(),

        ]);
    }

    #[Route('/show/{id}', name: 'article_show')]
    public function show(int $id, ArticleRepository $ArticleRepository, Request $request, EntityManagerInterface $em): Response
    {

        // Role : Affiche le detail d'un article publié et le formulaire de commentaire
        // Affichage le detail de l'article, le formulaire de commentaire et la liste des commentaires, le nombre de like
        // Retourne l'objet article ainsi que le formulaire de commentaire

        $article = $ArticleRepository->find($id);
        if (!$article) {
            $this->addFlash('error', 'Article introuvable.');
            throw $this->createNotFoundException('Article not found');
        }
        if (!$article->isPublie()) {
            $this->addFlash('error', 'Cet article n\'est pas encore publié.');
            throw $this->createNotFoundException('Article not published');
        }   

        $comment = new Commentaire();
        $commentForm = $this->createForm(CommentType::class, $comment);
        $commentForm->handleRequest($request);

        if ($commentForm->isSubmitted() && $commentForm->isValid()) {
            $comment->setArticle($article);
            $comment->setAuteur($this->getUser());
            $comment->setDatePublication(new \DateTime());

            $em->persist($comment);
            $em->flush();

            $this->addFlash('success', 'Commentaire ajouté avec succès !');
            return $this->redirectToRoute('article_show', ['id' => $id]);
        } elseif ($commentForm->isSubmitted() && !$commentForm->isValid()) {
            $this->addFlash('error', 'Erreur lors de l\'ajout du commentaire. Veuillez réessayer.');
        }

        return $this->render('article/show.html.twig', [
            'article' => $article,
            'commentForm' => $commentForm->createView(),
        ]);
    }

    #[Route('/create', name: 'article_create')]
    public function create(Request $resquest, EntityManagerInterface $em): Response
    {
        // Role : Affiche un formulaire de création d'article
        // Création d'un nouvel article avec un titre, un chapeau, un contenu, une image et une catégorie et status de publication
        // Enregistrement de l'article dans la bdd avec l'utilisateur connecté comme auteur plus date de création et de modification
        // Retourne le formulaire de création d'article.

        $article = new Article();
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($resquest);

        if ($form->isSubmitted() == true && $form->isValid()) {

            $image = $form->get('imageFile')->getData();
            if ($image !== null ) {
                $fileName = uniqid() . '.' . $image->guessExtension();
                $image->move($this->getParameter('image_directory').'/articles', $fileName);
                $article->setImage($fileName);
            }

            $now = new \DateTime();
            $article->setDateCreation($now);
            $article->setDateModification($now);
            
            $article->setAuteur($this->getUser());

            $em->persist($article);
            $em->flush();
            $this->addFlash('success', 'Article créé avec succès !');
            return $this->redirectToRoute('article_user_list');

        } elseif ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Veuillez corriger les erreurs dans le formulaire.');
        }

        return $this->render('article/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/edit/{id}', name: 'article_edit')]
    public function edit(int $id, Request $request, EntityManagerInterface $em, ArticleRepository $articleRepository): Response
    {
        // Role : Affiche un formulaire de modification d'article prérempli
        // Modification de l'article seulement si on est l'auteur de celui-ci avec un titre, un chapeau, un contenu, une image et une catégorie et status de publication
        // Enregistrement de l'article avec date de création et de modification et si nouvelle image, suppression de l'ancienne
        // Retourne le formulaire de modification d'article et l'objet de l'article modifié

        $article = $articleRepository->find($id);

        if (!$article) {
            $this->addFlash('error', 'Article introuvable.');
            throw $this->createNotFoundException('Article not found');
        }

        if ($article->getAuteur() !== $this->getUser()) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à modifier cet article.');
            $this->redirectToRoute('article_index');
        }

        $oldImage = $article->getImage();

        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $image = $form->get('imageFile')->getData();
            if ($image !== null) {
                if ($oldImage) {
                    $oldImagePath = $this->getParameter('image_directory') . '/articles/' . $oldImage;
                    if (file_exists($oldImagePath)) {
                        @unlink($oldImagePath);
                    }
                }
                $fileName = uniqid() . '.' . $image->guessExtension();
                $image->move($this->getParameter('image_directory') . '/articles', $fileName);
                $article->setImage($fileName);
            }

            $article->setDateModification(new \DateTime());

            $em->persist($article);
            $em->flush();

            $this->addFlash('success', 'Article modifié avec succès !');
            return $this->redirectToRoute('article_user_list');

        } elseif ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'Veuillez corriger les erreurs dans le formulaire.');
        }

        return $this->render('article/editer.html.twig', [
            'form' => $form->createView(),
            'article' => $article,
        ]);
    }

    #[Route('/toggle-publish/{id}', name: 'article_toggle_publish', methods: ['POST'])]
    public function togglePublish(int $id, ArticleRepository $articleRepository, EntityManagerInterface $em): Response
    {
        // Role : Permet de basculer l'état de publication d'un article
        // Si l'article est publié, il devient non publié et vice versa

        $article = $articleRepository->find($id);
        
        if (!$article) {
            $this->addFlash('error', 'Article introuvable.');
            throw $this->createNotFoundException('Article not found');
        }

        if ($article->getAuteur() !== $this->getUser()) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à modifier cet article.');
            $this->redirectToRoute('article_index');
        }

        $article->setPublie(!$article->isPublie());

        $em->persist($article);
        $em->flush();

        if ($article->isPublie()) {
            $this->addFlash('success', 'Article publié avec succès !');
        } else {
            $this->addFlash('success', 'Article dépublié avec succès !');
        }

        return $this->redirectToRoute('article_user_list');
    }

    #[Route('/toggle-like/{id}', name: 'article_toggle_like', methods: ['POST'])]
    public function toggleLike(int $id, ArticleRepository $articleRepository, EntityManagerInterface $em, Request $request): Response
    {
        // Role : Permet de basculer l'état de like d'un article
        // Si l'article est aimé, il devient non aimé et vice versa depuis une requête POST en js
        // retourne un JSON avec le statut du like et le nombre de likes et si la requête est successful

        $article = $articleRepository->find($id);
        
        if (!$article) {
            return $this->json(['error' => 'Article not found'], 404);
        }

        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'User not authenticated'], 401);
        }

        $isLiked = $article->getLiked()->contains($user);
        
        if ($isLiked) {
            $article->removeLiked($user);
            $liked = false;
        } else {
            $article->addLiked($user);
            $liked = true;
        }

        $em->persist($article);
        $em->flush();

        return $this->json([
            'success' => true,
            'liked' => $liked,
            'likeCount' => $article->getLiked()->count()
        ]);
    }
}
