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
    public function list(ArticleRepository $ArticleRepository, Request $resquest, CategorieRepository $CategorieRepository): Response
    {
        $searchData = '';
        $form = $this->createForm(SearchType::class);
        $form->handleRequest($resquest);

        if ($form->isSubmitted() == true && $form->isValid()) {
            $searchData = $form->get('q')->getData();
            $articles = $ArticleRepository->findBySearch($searchData);
        } else {
            $articles = $ArticleRepository->findAll();
        }

        $categories = $CategorieRepository->findAll();


        return $this->render('article/list.html.twig', [
            'articles' => $articles,
            'categories' => $categories,
            'form' => $form->createView()
        ]);
    }

    #[Route('/user-list', name: 'article_user_list')]
    public function userList(ArticleRepository $ArticleRepository, Request $resquest, CategorieRepository $CategorieRepository): Response
    {
        $searchData = '';
        $form = $this->createForm(SearchType::class);
        $form->handleRequest($resquest);

        if ($form->isSubmitted() == true && $form->isValid()) {
            $searchData = $form->get('q')->getData();
            $articles = $ArticleRepository->findBySearch($searchData);
        } else {
            $articles = $ArticleRepository->findAll();
        }

        $categories = $CategorieRepository->findAll();


        return $this->render('article/user_list.html.twig', [
            'articles' => $articles,
            'categories' => $categories,
            'form' => $form->createView()
        ]);
    }

    #[Route('show/{id}', name: 'article_show')]
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

        // get comments for the article
        $comments = $article->getComments();

        return $this->render('article/show.html.twig', [
            'article' => $article,
            'comments' => $comments
        ]);
    }

    #[Route('create', name: 'article_create')]
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

            $em->persist($article);
            $em->flush();
        }

        return $this->render('article/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
