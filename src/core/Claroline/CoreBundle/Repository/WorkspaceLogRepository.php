<?php

namespace Claroline\CoreBundle\Repository;

use Claroline\CoreBundle\Entity\User;
use Doctrine\ORM\EntityRepository;

class WorkspaceLogRepository extends EntityRepository
{
    public function findLatestWorkspaceByUser(User $user, $size = 5)
    {
        $dql = "
            SELECT DISTINCT w AS workspace, MAX(l.date) AS max_date
            FROM Claroline\CoreBundle\Entity\Workspace\AbstractWorkspace w
            INNER JOIN Claroline\CoreBundle\Entity\Workspace\WorkspaceLog l WITH l.workspace = w
            JOIN l.user u
            WHERE l.type = 'workspace_access'
            AND u.id = :userId
            GROUP BY w.id
            ORDER BY max_date DESC
        ";

        $query = $this->_em->createQuery($dql);
        $query->setMaxResults($size);
        $query->setParameter('userId', $user->getId());

        return $query->getResult();
    }
}