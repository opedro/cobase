<?php

namespace Cobase\AppBundle\Repository;

use Cobase\AppBundle\Entity\Group;
use Cobase\AppBundle\Entity\Post;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityRepository;
use Cobase\UserBundle\Entity\User;

/**
 * PostRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class PostRepository extends EntityRepository
{
    /**
     * @param Group $group
     * @param integer $limit
     */
    public function getLatestPublicPostsForGroup(Group $group, $limit = null)
    {
        $qb = $this->createQueryBuilder('b')
            ->select('b, g')
            ->leftJoin('b.group', 'g')
            ->addOrderBy('b.created', 'DESC')
            ->andWhere('g.isPublic = :status')
            ->setParameter('status', '1')
            ->andWhere('b.group = :group')
            ->setParameter('group', $group);

        if (false === is_null($limit))
            $qb->setMaxResults($limit);

        return $qb->getQuery()
            ->getResult();
    }

    /**
     * @param Group $group
     * @return \Doctrine\ORM\Query
     */
    public function getLatestPublicPostsForGroupQuery(Group $group)
    {
        $em = $this->getEntityManager();

        $dql = 'SELECT p FROM Cobase\AppBundle\Entity\Post p
                JOIN p.user u
                WHERE p.group = :group
                ORDER BY p.created DESC';

        return $em->createQuery($dql)
            ->setParameters(
                array('group' => $group)
            );
    }

    /**
     * @TODO refactor to minimize code duplication, see getAllPostsForPublicGroups()
     * Get given amount of latest posts for public groups for any user
     *
     * @param null $limit
     * @return array
     */
    public function getLatestPostsForPublicGroups($limit = null)
    {
        $qb = $this->createQueryBuilder('b')
            ->select('b, c')
            ->leftJoin('b.group', 'c')
            ->addOrderBy('b.created', 'DESC')
            ->andWhere('c.isPublic = ?1')
            ->setParameter('1', '1');

        if (false === is_null($limit))
            $qb->setMaxResults($limit);

        return $qb->getQuery()
            ->getResult();
    }

    /**
     * @TODO refactor to minimize code duplication, see getLatestPostsForPublicGroups()
     *
     * Get all group posts for public groups with given sort options
     *
     * @param null $limit
     * @param string $order
     * @return array
     */
    public function getAllPostsForPublicGroups($limit = null, $order = 'ASC')
    {
        $qb = $this->createQueryBuilder('b')
            ->select('b, c')
            ->leftJoin('b.group', 'c')
            ->addOrderBy('b.created', $order)
            ->andWhere('c.isPublic = ?1')
            ->setParameter('1', '1');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()
            ->getResult();
    }

    /**
     * Find post by it's groupId and postId
     *
     * @param Group $group
     * @param $postId
     * @return array
     */
    public function findPostByGroupAndPostId(Group $group, $postId)
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        $qb->select('p')
            ->from('Cobase\AppBundle\Entity\Post', 'p')
            ->where('p.id = :postId')
            ->setParameter('postId', $postId)
            ->andWhere('p.group = :group')
            ->setParameter('group', $group);

        return $qb->getQuery()->getResult();
    }

    /**
     * Find group posts for a given user
     *
     * @param \Cobase\UserBundle\Entity\User $user
     * @return array
     */
    public function findAllForUser(User $user)
    {
        $qb = $this->createQueryBuilder('b')
            ->select('b, c')
            ->leftJoin('b.group', 'c')
            ->addOrderBy('b.created', 'DESC')
            ->andWhere('c.user = ?1')
            ->setParameter('1', $user);

        return $qb->getQuery()
            ->getResult();
    }

    /**
     * @param Post $post
     *
     * @return integer
     */
    public function getLikeCount(Post $post)
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        $qb->select('count(l)')
            ->from('Cobase\AppBundle\Entity\Like', 'l')
            ->where('l.resourceId = :id')
            ->setParameter('id', $post->getId())
            ->andWhere('l.resourceType = :type')
            ->setParameter('type', 'post');

        try {
            return $qb->getQuery()->getSingleScalarResult();
        } catch(NoResultException $e) {
            return 0;
        }
    }

    /**
     * @param Post $post
     * @return array
     */
    public function getLikes(Post $post)
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        $qb->select('l')
            ->from('Cobase\AppBundle\Entity\Like', 'l')
            ->leftJoin('l.user', 'u')
            ->where('l.resourceId = :id')
            ->setParameter('id', $post->getId())
            ->andWhere('l.resourceType = :type')
            ->setParameter('type', 'post')
            ->orderBy('u.name');

        return $qb->getQuery()->getResult();
    }
}
