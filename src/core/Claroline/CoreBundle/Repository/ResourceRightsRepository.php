<?php

namespace Claroline\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Claroline\CoreBundle\Entity\Resource\AbstractResource;

class ResourceRightsRepository extends EntityRepository
{
    /**
     * Used by the ResourceVoter.
     *
     * @param array[string]     $rights
     * @param AbstractResource  $resource
     *
     * @return ResourceRights;
     */
    public function findMaximumRights(array $roles, AbstractResource $resource)
    {
        if (count($roles) === 0) {
            throw new \RuntimeException('Roles cannot be empty');
        }

        $dql = '
            SELECT
                MAX (CASE rrw.canEdit WHEN true THEN 1 ELSE 0 END) as canEdit,
                MAX (CASE rrw.canOpen WHEN true THEN 1 ELSE 0 END) as canOpen,
                MAX (CASE rrw.canDelete WHEN true THEN 1 ELSE 0 END) as canDelete,
                MAX (CASE rrw.canCopy WHEN true THEN 1 ELSE 0 END) as canCopy,
                MAX (CASE rrw.canExport WHEN true THEN 1 ELSE 0 END) as canExport
            FROM Claroline\CoreBundle\Entity\Resource\ResourceRights rrw
            JOIN rrw.role role
            JOIN rrw.resource resource
            WHERE ';

        $index = 0;

        foreach ($roles as $role) {
            $dql .= $index !== 0 ? ' OR ' : '';
            $dql .= "resource.id = {$resource->getId()} AND role.name = '{$role}'";
            ++$index;
        }

        $query = $this->_em->createQuery($dql);

        return $query->getSingleResult();
    }

    public function findCreationRights(array $roles, AbstractResource $resource)
    {
        if (count($roles) === 0) {
            throw new \RuntimeException('Roles cannot be empty');
        }

        $dql = '
            SELECT DISTINCT type.name
            FROM Claroline\CoreBundle\Entity\Resource\ResourceType type
            JOIN type.rights right
            JOIN right.role role
            JOIN right.resource resource
            WHERE ';

        $index = 0;

        foreach ($roles as $role) {
            $dql .= $index !== 0 ? ' OR ' : '';
            $dql .= "resource.id = {$resource->getId()} AND role.name = '{$role}'";
            ++$index;
        }

        $query = $this->_em->createQuery($dql);

        return $query->getArrayResult();
    }

    public function findNonAdminRights(AbstractResource $resource)
    {
        $dql = "
            SELECT rights
            FROM Claroline\CoreBundle\Entity\Resource\ResourceRights rights
            JOIN rights.resource resource
            JOIN rights.role role
            JOIN rights.workspace workspace
            WHERE resource.id = {$resource->getId()}
            AND role.name != 'ROLE_ADMIN'
            AND resource.workspace = workspace
            ORDER BY role.name
        ";

        return $this->_em->createQuery($dql)->getResult();
    }
}