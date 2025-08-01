<?php

namespace App\Repository;

use App\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    //    /**
    //     * @return Article[] Returns an array of Article objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Article
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

        public function findBySearch($search): array
        {
            return $this->createQueryBuilder('a')
                ->andWhere('a.publie = true')
                ->andWhere('(a.titre LIKE :search OR a.chapeau LIKE :search OR a.contenu LIKE :search)')
                ->setParameter('search', '%' . $search . '%')
                ->orderBy('a.date_creation', 'DESC')
                ->getQuery()
                ->getResult();
        }

        public function findBySearchAndCategory($search, ?int $categoryId): array
        {
            $qb = $this->createQueryBuilder('a')
                ->andWhere('a.publie = true')
                ->andWhere('(a.titre LIKE :search OR a.chapeau LIKE :search OR a.contenu LIKE :search)')
                ->setParameter('search', '%' . $search . '%');

            if ($categoryId) {
                $qb->andWhere('a.categorie = :categoryId')
                    ->setParameter('categoryId', $categoryId);
            }

            return $qb->orderBy('a.date_creation', 'DESC')
                ->getQuery()
                ->getResult();
        }

        public function findBySearchAndAuthor($search, $author): array
        {
            return $this->createQueryBuilder('a')
                ->andWhere('a.auteur = :author')
                ->andWhere('(a.titre LIKE :search OR a.chapeau LIKE :search OR a.contenu LIKE :search)')
                ->setParameter('search', '%' . $search . '%')
                ->setParameter('author', $author)
                ->orderBy('a.date_creation', 'DESC')
                ->getQuery()
                ->getResult();
        }

        public function findBySearchAndCategoryAndAuthor($search, ?int $categoryId, $author): array
        {
            $qb = $this->createQueryBuilder('a')
                ->andWhere('a.auteur = :author')
                ->andWhere('(a.titre LIKE :search OR a.chapeau LIKE :search OR a.contenu LIKE :search)')
                ->setParameter('search', '%' . $search . '%')
                ->setParameter('author', $author);

            if ($categoryId) {
                $qb->andWhere('a.categorie = :categoryId')
                    ->setParameter('categoryId', $categoryId);
            }

            return $qb->orderBy('a.date_creation', 'DESC')
                ->getQuery()
                ->getResult();
        }

        public function findMostLikedByPeriod(?string $period = null): ?Article
        {
            $qb = $this->createQueryBuilder('a')
                ->leftJoin('a.liked', 'l')
                ->andWhere('a.publie = true');

            if ($period) {
                $qb->andWhere('a.date_creation >= :date')
                ->setParameter('date', new \DateTime($period));
            }

            $qb->groupBy('a.id')
            ->orderBy('COUNT(l.id)', 'DESC')
            ->setMaxResults(1);

            return $qb->getQuery()->getOneOrNullResult();
        }

        public function findMostLikedInLast24HoursOrWeekOrAllTime(): ?Article
        {
            $article = $this->findMostLikedByPeriod('-24 hours');
            if ($article) {
                return $article;
            }

            $article = $this->findMostLikedByPeriod('-7 days');
            if ($article) {
                return $article;
            }

            return $this->findMostLikedByPeriod();
        }
}
