<?php

namespace Claroline\CoreBundle\Library\Manager;

use Doctrine\ORM\EntityManager;
use Claroline\CoreBundle\Entity\File;
use Claroline\CoreBundle\Form\FileType;
use Symfony\Component\HttpFoundation\Response;

class FileManager
{
    /**
     * @var EntityManager
     */
    private $em;
    
    
    /**
     * @var string
     */
    private $dir;
    
    
    public function __construct(EntityManager $em, $dir)
    {   
        $this->em = $em; 
        $this->dir = $dir;
    }
    
    public function setDownloadResponseById($id, Response $response)
    {
        $file = $this->em->getRepository('ClarolineCoreBundle:File')->find($id);
        $response = $this->setDownloadResponse($file, $response);
        
        return $response;
    }
         
    public function setDownloadResponse(File $file, Response $response)
    {
        $response = $this->setDownloadHeaders($file, $response);
        return $response;
    }
    
    public function upload($tmpFile, $fileName)
    {
         $size = filesize($tmpFile);
         $hashName = hash('md5', $tmpFile . time());
         $tmpFile->move($this->dir, $hashName);
         $file = new File();
         $file->setSize($size);
         $file->setName($fileName);
         $file->setHashName($hashName);
         $this->em->persist($file);
         $this->em->flush();
    }

    public function deleteById($id)
    {
        $file = $this->em->getRepository('ClarolineCoreBundle:File')->find($id);
        $this->delete($file);
    }
       
    public function delete(File $file)
    {
        $this->removeFile($file);
        $this->em->remove($file);
        $this->em->flush();
    }
     
    public function findAll()
    {
        $files = $this->em->getRepository('ClarolineCoreBundle:File')->findAll();
        
        return $files;
    }
    
    public function findById($id)
    {
        $file = $this->em->getRepository('ClarolineCoreBundle:File')->find($id);
        
        return $file;
    }
    
    private function removeFile(File $file)
    {
        $pathName = $this->dir . DIRECTORY_SEPARATOR . $file->getHashName();
        chmod($pathName, 0777);
        unlink($pathName);
        $this->em->remove($file);
        $this->em->flush();
    }
    
    private function setDownloadHeaders(File $file, Response $response)
    {
        $response->setContent(file_get_contents($this->dir . DIRECTORY_SEPARATOR . $file->getHashName()));
        $response->headers->set('Content-Transfer-Encoding', 'octet-stream');
        $response->headers->set('Content-Type', 'application/force-download');
        $response->headers->set('Content-Disposition', 'attachment; filename=' . $file->getName());
        $response->headers->set('Content-Length', $file->getSize());
        $response->headers->set('Content-Type', 'application/' .  pathinfo($file->getName(), PATHINFO_EXTENSION));
        $response->headers->set('Connection', 'close');
        
        return $response;
    }
}
