<?php

namespace App\Controller;

use App\Entity\Article;
use App\Form\SearchType;
use App\Form\ArticleType;
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

        // Get search term if form is submitted
        if ($form->isSubmitted() && $form->isValid()) {
            $searchData = $form->get('q')->getData();
        }

        // Use repository method that combines search and category filter
        if (!empty($searchData) && $categoryId) {
            // Both search term and category filter
            $articles = $ArticleRepository->findBySearchAndCategory($searchData, $categoryId);
        } elseif (!empty($searchData)) {
            // Only search term
            $articles = $ArticleRepository->findBySearch($searchData);
        } elseif ($categoryId) {
            // Only category filter
            $articles = $ArticleRepository->findBy(['categorie' => $categoryId, 'publie' => true]);
        } else {
            // No filters, show all published articles
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

        // Get search term if form is submitted
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
    public function userShow(int $id, ArticleRepository $ArticleRepository): Response
    {
        $article = $ArticleRepository->find($id);
        if (!$article) {
            throw $this->createNotFoundException('Article not found');
        }

        // Only allow access if the current user is the author
        if ($article->getAuteur() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You are not allowed to view this article');
        }

        return $this->render('article/show.html.twig', [
            'article' => $article,
        ]);
    }



    #[Route('/show/{id}', name: 'article_show')]
    public function show(int $id, ArticleRepository $ArticleRepository): Response
    {
        $article = $ArticleRepository->find($id);
        if (!$article) {
            throw $this->createNotFoundException('Article not found');
        }
        // Check if the article is published
        if (!$article->isPublie()) {
            throw $this->createNotFoundException('Article not published');
        }   

        return $this->render('article/show.html.twig', [
            'article' => $article,
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

            // Set creation and modification dates
            $now = new \DateTime();
            $article->setDateCreation($now);
            $article->setDateModification($now);
            
            // Set the author as the current user
            $article->setAuteur($this->getUser());

            $em->persist($article);
            $em->flush();
            $this->addFlash('success', 'Article created successfully!');
            return $this->redirectToRoute('article_user_list');

        }

        return $this->render('article/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/toggle-publish/{id}', name: 'article_toggle_publish', methods: ['POST'])]
    public function togglePublish(int $id, ArticleRepository $articleRepository, EntityManagerInterface $em): Response
    {
        $article = $articleRepository->find($id);
        
        if (!$article) {
            throw $this->createNotFoundException('Article not found');
        }

        // Only allow the author to toggle publication status
        if ($article->getAuteur() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You are not allowed to modify this article');
        }

        // Toggle the publication status
        $article->setPublie(!$article->isPublie());
        
        // Update modification date
        $article->setDateModification(new \DateTime());

        $em->persist($article);
        $em->flush();

        // Add flash message
        if ($article->isPublie()) {
            $this->addFlash('success', 'Article publié avec succès !');
        } else {
            $this->addFlash('success', 'Article dépublié avec succès !');
        }

        // Redirect back to the user list
        return $this->redirectToRoute('article_user_list');
    }
}
