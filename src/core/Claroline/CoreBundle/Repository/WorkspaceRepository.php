<?php

namespace Claroline\CoreBundle\Repository;

use Claroline\CoreBundle\Entity\User;
use Doctrine\ORM\EntityRepository;

class WorkspaceRepository extends EntityRepository
{
    public function findByUser(User $user)
    {
        $dql = "
            SELECT w, r FROM Claroline\CoreBundle\Entity\Workspace\AbstractWorkspace w
            JOIN w.roles r
            JOIN r.users u
            WHERE u.id = :userId
        ";
        $query = $this->_em->createQuery($dql);
        $query->setParameter('userId', $user->getId());

        return $query->getResult();
    }

    public function findNonPersonal()
    {
        $dql = "
            SELECT w FROM Claroline\CoreBundle\Entity\Workspace\AbstractWorkspace w
            WHERE w.id NOT IN (
                SELECT w1.id FROM Claroline\CoreBundle\Entity\Workspace\AbstractWorkspace w1
                JOIN w1.personalUser pu
            )
        ";

        $query = $this->_em->createQuery($dql);

        return $query->getResult();
    }

    public function findByAnonymous()
    {
        $dql = "
            SELECT DISTINCT w FROM Claroline\CoreBundle\Entity\Workspace\AbstractWorkspace w
            JOIN w.workspaceOrderedTools wot
            JOIN wot.workspaceToolRoles wtr
            JOIN wtr.role r
            WHERE r.name = 'ROLE_ANONYMOUS'";

            $query = $this->_em->createQuery($dql);

            return $query->getResult();
    }

    public function count()
    {
        $dql = "SELECT COUNT(w) FROM Claroline\CoreBundle\Entity\Workspace\AbstractWorkspace w";
        $query = $this->_em->createQuery($dql);

        return $query->getSingleScalarResult();
    }

    public function findByRoles(array $roles)
    {
        $dql = "
            SELECT DISTINCT w FROM Claroline\CoreBundle\Entity\Workspace\AbstractWorkspace w
            JOIN w.workspaceOrderedTools wot
            JOIN wot.workspaceToolRoles wtr
            JOIN wtr.role r
            WHERE r.name = '{$roles[0]}'";

        for ($i = 1, $size = count($roles); $i < $size; $i++) {
            $dql .= " OR r.name = '{$roles[$i]}'";
        }

        $query = $this->_em->createQuery($dql);

        return $query->getResult();
    }
}