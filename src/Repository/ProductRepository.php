<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Returns top ordered products with summed quantities.
     *
     * @return list<array{product: Product, orderedQty: int}>
     */
    public function findTopSellingWithQuantities(int $limit = 6): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p AS product, COALESCE(SUM(oi.quantity), 0) AS orderedQty')
            ->innerJoin('p.orderItems', 'oi')
            ->groupBy('p.id')
            ->orderBy('orderedQty', 'DESC')
            ->addOrderBy('p.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            $product = $row['product'] ?? null;
            if (!$product instanceof Product) {
                continue;
            }
            $result[] = [
                'product' => $product,
                'orderedQty' => (int) ($row['orderedQty'] ?? 0),
            ];
        }

        return $result;
    }

    //    /**
    //     * @return Product[] Returns an array of Product objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Product
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
