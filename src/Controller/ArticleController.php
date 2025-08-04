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
        // Article à la une = le plus liké dans les dernières 24h sinon dans la semaine ou de tous les temps
        $articleUne = $ArticleRepository->findMostLikedInLast24HoursOrWeekOrAllTime();

        // 4 articles les plus récents publiés
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
        $article = $ArticleRepository->find($id);
        if (!$article) {
            $this->addFlash('error', 'Article introuvable.');
            throw $this->createNotFoundException('Article not found');
        }

        if ($article->getAuteur() !== $this->getUser()) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à consulter cet article.');
            throw $this->createAccessDeniedException('You are not allowed to view this article');
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
        $article = $articleRepository->find($id);

        if (!$article) {
            $this->addFlash('error', 'Article introuvable.');
            throw $this->createNotFoundException('Article not found');
        }

        if ($article->getAuteur() !== $this->getUser()) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à modifier cet article.');
            throw $this->createAccessDeniedException('You are not allowed to edit this article');
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
        $article = $articleRepository->find($id);
        
        if (!$article) {
            $this->addFlash('error', 'Article introuvable.');
            throw $this->createNotFoundException('Article not found');
        }

        if ($article->getAuteur() !== $this->getUser()) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à modifier cet article.');
            throw $this->createAccessDeniedException('You are not allowed to modify this article');
        }

        $article->setPublie(!$article->isPublie());
        $article->setDateModification(new \DateTime());

        $em->persist($article);
        $em->flush();

        // Add flash message
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
