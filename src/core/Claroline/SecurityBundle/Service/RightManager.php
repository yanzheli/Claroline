<?php

namespace Claroline\SecurityBundle\Service;

use Symfony\Component\Security\Acl\Dbal\AclProvider;
use Symfony\Component\Security\Acl\Permission\MaskBuilder;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Symfony\Component\Security\Acl\Exception\AclNotFoundException;
use Symfony\Component\Security\Acl\Permission\BasicPermissionMap;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\UnitOfWork;
use Claroline\UserBundle\Entity\User;
use Claroline\SecurityBundle\Entity\Role;
use Claroline\SecurityBundle\Acl\Domain\ClassIdentity;
use Claroline\SecurityBundle\Service\Exception\RightManagerException;

// TODO : 
// - solve class permissions issuess
// - implement instance/class field permissions methods for users and roles

class RightManager
{
    private $em;
    private $conn;
    private $aclProvider;
    private $aclTables;
    private $permissionMap;
    
    public function __construct(EntityManager $em, AclProvider $provider, array $aclTableNames)
    {
        $this->em = $em;
        $this->conn = $em->getConnection();
        $this->aclProvider = $provider;
        $this->aclTables = $aclTableNames;
        $this->permissionMap = new BasicPermissionMap(); // not a public service
    }
    
    public function createEntityWithOwner($newEntity, User $owner)
    {
        $this->checkEntityState($newEntity, UnitOfWork::STATE_NEW);
        $this->checkEntityState($owner, UnitOfWork::STATE_MANAGED);
        
        $this->em->persist($newEntity);
        $this->em->flush();
        
        $this->setEntityPermissionsForUser($newEntity, MaskBuilder::MASK_OWNER, $owner);
    }
    
    public function getEntityOwner($entity)
    {
        $owners = $this->getAllowedUsersOnEntityByMask($entity, MaskBuilder::MASK_OWNER);
        
        if (count($owners) > 1)
        {
            throw new RightManagerException(
                'The entity ' . get_class($entity)
                . "with id {$entity->getId()} has mutliple owners.",
                RightManagerException::MULTIPLE_OWNERS_ENTITY
            );
        }
        
        return count($owners) > 0 ? $owners[0] : false;
    }
    
    public function setEntityOwner($managedEntity, User $user, $oldOwnerNewPermissionMask = null)
    {
        $oldOwner = $this->getEntityOwner($managedEntity);
         
        if ($oldOwner !== false)
        {
            if ($oldOwnerNewPermissionMask === null)
            {
                $this->deleteEntityPermissionsForUser($managedEntity, $oldOwner);
            }
            else
            {
                $this->setEntityPermissionsForUser(
                    $managedEntity, 
                    $oldOwnerNewPermissionMask,
                    $oldOwner
                );
            }
        }
        
        $this->setEntityPermissionsForUser($managedEntity, MaskBuilder::MASK_OWNER, $user);
    }
    
    public function setEntityPermissionsForUser($managedEntity, $permissionMask, User $user)
    {
        $this->checkEntityState($managedEntity, UnitOfWork::STATE_MANAGED);
        $this->checkEntityState($user, UnitOfWork::STATE_MANAGED);
        $this->checkPermissionMask($permissionMask);
        
        if ($permissionMask === MaskBuilder::MASK_OWNER)
        {
            $owner = $this->getEntityOwner($managedEntity);
            
            if ($owner !== false)
            {
                if ($owner == $user)
                {
                    return;
                }
                
                $entityType = get_class($managedEntity);
            
                throw new RightManagerException(
                    "There's already an owner for entity {$entityType} with id "
                    . "{$managedEntity->getId()}. Use RightManager::setEntityOwner().",
                    RightManagerException::MULTIPLE_OWNERS_ATTEMPT
                );
            }
        }
        
        $objectIdentity = ObjectIdentity::fromDomainObject($managedEntity);
        $userIdentity = UserSecurityIdentity::fromAccount($user);
        $acl = $this->createAclIfNotExists($objectIdentity);        
        $aces = $acl->getObjectAces();
        $aclKnowsUserIdentity = false;
        
        foreach ($aces as $aceIndex => $ace)
        {
            if ($ace->getSecurityIdentity() == $userIdentity)
            {
                $aclKnowsUserIdentity = true; 
                $acl->updateObjectAce($aceIndex, $permissionMask);
                break;
            }
        }
        
        if (count($aces) == 0 || ! $aclKnowsUserIdentity)
        {
            $acl->insertObjectAce($userIdentity, $permissionMask);
        }       
        
        $this->aclProvider->updateAcl($acl);
    }
    
    public function deleteEntityPermissionsForUser($managedEntity, User $user)
    {
        $this->checkEntityState($managedEntity, UnitOfWork::STATE_MANAGED);
        $this->checkEntityState($user, UnitOfWork::STATE_MANAGED);
        
        $objectIdentity = ObjectIdentity::fromDomainObject($managedEntity);
        $userIdentity = UserSecurityIdentity::fromAccount($user);
        
        try
        {
            $acl = $this->aclProvider->findAcl($objectIdentity);
            $aces = $acl->getObjectAces();
        
            foreach ($aces as $aceIndex => $ace)
            {
                if ($ace->getSecurityIdentity() == $userIdentity)
                {
                    $acl->deleteObjectAce($aceIndex);
                }
            }
            
            $this->aclProvider->updateAcl($acl);
        }
        catch (AclNotFoundException $ex)
        {
            return;
        }
    }
    
    public function deleteEntityAndPermissions($managedEntity)
    {
        $this->checkEntityState($managedEntity, UnitOfWork::STATE_MANAGED);
        
        $objectIdentity = ObjectIdentity::fromDomainObject($managedEntity);
        
        $this->em->remove($managedEntity);
        $this->em->flush();
        $this->aclProvider->deleteAcl($objectIdentity);
    }
    
    public function getAllowedUsersOnEntityByMask($entity, $permissionMask)
    {
        $this->checkHasGetIdMethod($entity);
        
        return $this->doGetAllowedUsersOnEntity($entity, $permissionMask, true);
    }
    
    public function getAllowedUsersOnEntityByPermission($entity, $permission)
    {
        $lowestMatchingMask = $this->getLowestMaskForPermission($permission);
        
        return $this->doGetAllowedUsersOnEntity($entity, $lowestMatchingMask, false);
    }
    
    /*
    public function setClassPermissionsForUser($entityFQCN, $permissionMask, User $user)
    {
        $this->checkEntityState($user, UnitOfWork::STATE_MANAGED);
        $this->checkPermissionMask($permissionMask);
        
        $fqcnParts = explode('\\', $entityFQCN);
        $identifier = array_pop($fqcnParts);
        $userIdentity = UserSecurityIdentity::fromAccount($user);
        $objectIdentity = ClassIdentity::fromDomainClass($entityFQCN);
        $acl = $this->createAclIfNotExists($objectIdentity);        
        $aces = $acl->getClassAces();
        $aclKnowsUserIdentity = false;
        
        foreach ($aces as $aceIndex => $ace)
        {
            if ($ace->getSecurityIdentity() == $userIdentity)
            {
                $aclKnowsUserIdentity = true; 
                $acl->updateClassAce($aceIndex, $permissionMask);
                break;
            }
        }
        
        if (count($aces) == 0 || ! $aclKnowsUserIdentity)
        {
            $acl->insertClassAce($userIdentity, $permissionMask);
        }
        
        $this->aclProvider->updateAcl($acl);
    }
    
    public function setEntityPermissionsForRole($managedEntity, $permissionMask, Role $role)
    {
        
    }
    
    public function setClassPermissionsForRole($entityFQCN, $permissionMask, Role $role)
    {
        
    }
 
    public function getAllowedEntitiesIdsByUser($entityFQCN, User $user, $permissionMask = null)
    {
        $sql = "
            SELECT oid.object_identifier
            FROM {$this->aclTables['entry_table_name']} e  
            JOIN {$this->aclTables['oid_table_name']} oid ON (oid.id = e.object_identity_id)   
            JOIN {$this->aclTables['sid_table_name']} sid ON (sid.id = e.security_identity_id)
            JOIN {$this->aclTables['class_table_name']} c ON (c.id = oid.class_id)
            WHERE c.class_type = ?
            AND sid.username = ?
            AND sid.identifier = ?
            GROUP BY oid.object_identifier
        ";
         
        $statement = $this->conn->prepare($sql);        
        $isUsername = $this->conn->getDatabasePlatform()->convertBooleans(true);
        // See MutableAclProvider.php, line 513
        $securityIdentifier = "Claroline\UserBundle\Entity\User-{$user->getUsername()}";
        $statement->bindValue(1, $entityFQCN);
        $statement->bindValue(2, $isUsername);
        $statement->bindValue(3, $securityIdentifier);
                
        if (! $statement->execute())
        {
            throw new ClarolineException("The statement '{$sql}' couldn't be executed.");
        }
        
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getAllowedEntitiesIdsByRoles($entityFQCN, array $roles, $permissionMask = null)
    {
        
    }
    */
    
    /**
     * Helper method checking that a given entity is in a particular state regarding the
     * entity manager (e.g. : most acl operations require that the domain object has a
     * not null id, which is impossible in the case of a new/unmanaged entity).
     * 
     * @param object $entity
     * @param integer $expectedState 
     */
    private function checkEntityState($entity, $expectedState)
    {
        $entityState = $this->em->getUnitOfWork()->getEntityState($entity);
        
        if ($entityState !== $expectedState)
        {
            $entityType = get_class($entity);
            $entityType == 'Claroline\UserBundle\Entity\User' ? 
                $exceptionCode = RightManagerException::INVALID_USER_STATE :
                $exceptionCode = RightManagerException::INVALID_ENTITY_STATE;
            
            throw new RightManagerException(
                "The expected entity state for {$entityType} "
                . "was {$expectedState}, {$entityState} given.",
                $exceptionCode
            );
        }
    }
    
    /**
     * Helper method checking that a given mask is part of the Symfony built-in map.
     * 
     * @param integer $mask 
     */
    private function checkPermissionMask($mask)
    {
        try
        {
            MaskBuilder::getCode($mask);
        }
        catch (\Exception $ex)
        {
            throw new RightManagerException(
                "Invalid permission mask '{$mask}'. Use the built-in "
                . 'mask list defined in the MaskBuilder class.',
                RightManagerException::INVALID_PERMISSION_MASK
            );
        }
    }
    
    private function createAclIfNotExists($objectIdentity)
    {
        try
        {
            $acl = $this->aclProvider->findAcl($objectIdentity);
        }
        catch (AclNotFoundException $ex)
        {
            $acl = $this->aclProvider->createAcl($objectIdentity);
        }
        
        return $acl;
    }
    
    private function checkHasGetIdMethod($entity)
    {
        if (! method_exists($entity, 'getId'))
        {
            $entityType = get_class($entity);
            
            throw new RightManagerException(
                "Entity {$entityType} must have a getId() method",
                RightManagerException::NO_GET_ID_METHOD
            );
        }
    }
    
    /**
     * Helper method returning the lowest bitmask value for a given permission,
     * or false if the permission doesn't exist.
     * 
     * @param string $permission
     * @return integer | boolean
     */
    private function getLowestMaskForPermission($permission)
    {
        $masks = $this->permissionMap->getMasks($permission, null);
        
        if (is_array($masks))
        {
            sort($masks);
        
            return $masks[0];
        }
        
        return false;
    }
    
    private function doGetAllowedUsersOnEntity($entity, $permissionMask, $isMaskEqualitySearch)
    {
        $this->checkHasGetIdMethod($entity);
        
        $isMaskEqualitySearch === true ? $maskOperator = '=' : $maskOperator = '>=';
        
        $sql = "
            SELECT sid.identifier
            FROM {$this->aclTables['entry_table_name']} e  
            JOIN {$this->aclTables['oid_table_name']} oid ON (oid.id = e.object_identity_id)   
            JOIN {$this->aclTables['sid_table_name']} sid ON (sid.id = e.security_identity_id)
            JOIN {$this->aclTables['class_table_name']} c ON (c.id = oid.class_id)
            WHERE c.class_type = ?
            AND oid.object_identifier = ?
            AND e.mask {$maskOperator} ?
            AND sid.username = ?
            GROUP BY sid.identifier
        ";
            
        $statement = $this->conn->prepare($sql);
        $classType = get_class($entity);
        $objectIdentifier = $entity->getId();
        $isUsername = $this->conn->getDatabasePlatform()->convertBooleans(true);
        $statement->bindValue(1, $classType);
        $statement->bindValue(2, $objectIdentifier);
        $statement->bindValue(3, $permissionMask);
        $statement->bindValue(4, $isUsername);
                
        if (! $statement->execute())
        {
            throw new ClarolineException("The statement '{$sql}' couldn't be executed.");
        }
        
        $usernames = array();
        
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC))
        {
            $usernames[] = str_replace('Claroline\UserBundle\Entity\User-', '', $row['identifier']);
        }
        
        if (count($usernames) > 0)
        {
            $users = $this->em
                ->getRepository('Claroline\UserBundle\Entity\User')
                ->getUsersByUsernameList($usernames);
        
            return $users;
        }
        
        return array();
    }
}