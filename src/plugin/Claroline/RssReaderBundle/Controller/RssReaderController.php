<?php

namespace Claroline\RssReaderBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Claroline\RssReaderBundle\Form\ConfigType;
use Claroline\RssReaderBundle\Entity\Config;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RssReaderController extends Controller
{
    public function createConfigAction($workspaceId, $isDesktop, $isDefault, $userId)
    {
        $form = $this->get('form.factory')->create(new ConfigType(), new Config());
        $form->bindRequest($this->get('request'));
        $em = $this->get('doctrine.orm.entity_manager');

        if ($form->isValid()) {
            $config = $form->getData();
            $workspace = $em->getRepository('ClarolineCoreBundle:Workspace\AbstractWorkspace')->find($workspaceId);
            $user = $em->getRepository('ClarolineCoreBundle:User')->find($userId);
            $config->setWorkspace($workspace);
            $config->setUser($user);
            $config->setDesktop($isDesktop);
            $config->setDefault($isDefault);
            $em->persist($config);
            $em->flush();
        } else {
            return $this->render(
                'ClarolineRssReaderBundle::form_workspace_create.html.twig', array(
                'form' => $form->createView(),
                'workspaceId' => $workspaceId,
                'isDesktop' => $isDesktop,
                'isDefault' => $isDefault,
                'userId' => $userId
                )
            );
        }

        if ($config->getWorkspace() != null) {
            $url = $this->generateUrl(
                'claro_workspace_open',
                array('workspaceId' => $config->getWorkspace()->getId())
            );

            return new RedirectResponse($url);
        }

        if ($isDefault) {
            return new RedirectResponse($this->generateUrl('claro_admin_widgets'));
        }

        return new RedirectResponse($this->generateUrl('claro_desktop_open'));
    }

    public function updateConfigAction($configId)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $rssConfig = $em->getRepository('ClarolineRssReaderBundle:Config')->find($configId);

        if ($rssConfig->getWorkspace() !== null) {
            if (!$this->get('security.context')->isGranted('parameters', $rssConfig->getWorkspace())) {
                throw new AccessDeniedHttpException();
            }
        } elseif ($this->get('security.context')->getToken()->getUser() != $rssConfig->getUser()) {
            throw new AccessDeniedHttpException();
        }

        $form = $this->get('form.factory')->create(new ConfigType(), $rssConfig);
        $form->bindRequest($this->get('request'));

        if ($form->isValid()) {
            $config = $form->getData();
            ($config->getUrl() == '') ? $em->remove($config): $em->persist($config);
            $em->flush();
        } else {
            return $this->render(
                'ClarolineRssReaderBundle::form_workspace_update.html.twig', array(
                'form' => $form->createView(),
                'rssConfig' => $rssConfig
                )
            );
        }

        if ($rssConfig->getWorkspace() != null) {
            $url = $this->generateUrl(
                'claro_workspace_open',
                array('workspaceId' => $rssConfig->getWorkspace()->getId())
            );

            return new RedirectResponse($url);
        }

        if (!$rssConfig->isDefault() && $rssConfig->isDesktop()) {
            return new RedirectResponse($this->generateUrl('claro_desktop_open'));
        }

        return new RedirectResponse($this->generateUrl('claro_admin_widgets'));
    }
}
