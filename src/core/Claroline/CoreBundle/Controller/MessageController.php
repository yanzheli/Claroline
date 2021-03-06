<?php

namespace Claroline\CoreBundle\Controller;

use Claroline\CoreBundle\Entity\Message;
use Claroline\CoreBundle\Entity\UserMessage;
use Claroline\CoreBundle\Form\MessageType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

class MessageController extends Controller
{
    const MESSAGE_PER_PAGE = 20;

    /**
     * @Route(
     *     "/form/group/{groupId}",
     *     name="claro_message_form_for_group"
     * )
     *
     * Displays the message form. It'll be sent to every user of a group.
     * In order to do this, this methods redirects to the form creation controller
     * with a query string including every users of the group.
     *
     * @param integer $groupId
     *
     * @return Response
     */
    public function formForGroupAction($groupId)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $group = $em->find('ClarolineCoreBundle:Group', $groupId);
        $users = $em->getRepository('ClarolineCoreBundle:User')
            ->findByGroup($group);
        $urlParameters = '?';

        $i = 0;

        foreach ($users as $user) {
            if ($i > 0) {
                $urlParameters .= "&";
            }

            $urlParameters .= "ids[]={$user->getId()}";

            $i++;
        }

        return $this->redirect($this->generateUrl('claro_message_form') . $urlParameters);
    }

    /**
     * @Route(
     *     "/form",
     *     name="claro_message_form"
     * )
     *
     * Display the message form.
     * It takes a array of user ids (query string: ids[]=1&ids[]=2).
     * The "to" field of the form must be completed in the following way: username1; username2; username3
     * (the separator is ; and it requires the username).
     *
     * @return Response
     */
    public function formAction()
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $params = $this->get('request')->query->all();
        $usersString = '';

        if (isset($params['ids'])) {
            foreach ($params['ids'] as $id) {
                $user = $em->getRepository('ClarolineCoreBundle:User')
                    ->find($id);
                $usersString .= "{$user->getUsername()}; ";
            }
        }

        $form = $this->createForm(new MessageType($usersString));

        return $this->render(
            'ClarolineCoreBundle:Message:message_form.html.twig',
            array('form' => $form->createView())
        );
    }

    /**
     * @Route(
     *     "/send/{parentId}",
     *     name="claro_message_send",
     *     defaults={"parentId"=0}
     * )
     *
     * Handles the message form submission.
     *
     * @param integer $parentId the parent message (in a discussion, you can answer
     * to a message wich is the parent). The entity Message is a nested tree.
     * By default (no parent) $parentId = 0 (defined in the message.yml file).
     *
     * @todo: add success/error message
     *
     * @return Response
     */
    public function sendAction($parentId)
    {
        $user = $this->get('security.context')->getToken()->getUser();
        $request = $this->get('request');
        $em = $this->get('doctrine.orm.entity_manager');
        $form = $this->get('form.factory')->create(new MessageType(), new Message());
        $form->bind($request);

        if ($form->isValid()) {
            $message = $form->getData();
            $message->setUser($user);
            $message->setSenderUsername($user->getUsername());
            $parent = $em->getRepository('ClarolineCoreBundle:Message')->find($parentId);

            if ($parent != null) {
                $message->setParent($parent);
            }
            $em->persist($message);

            // create an UserMessage for the sender
            $userMessage = new UserMessage(true);
            $userMessage->setUser($user);
            $userMessage->setMessage($message);
            $em->persist($userMessage);

            $to = preg_replace('/\s+/', '', $form->get('to')->getData());

            if (substr($to, -1, 1) === ';') {
                $to = substr_replace($to, "", -1);
            }

            $usernames = explode(';', $to);
            foreach ($usernames as $username) {
                $user = $em->getRepository('ClarolineCoreBundle:User')
                    ->findOneBy(array('username' => $username));
                $userMessage = new UserMessage();
                $userMessage->setUser($user);
                $userMessage->setMessage($message);
                $receiversUsername = $message->getReceiverUsername();

                if (empty($receiversUsername)) {
                    $receiversUsername = $username;

                } else {
                    $receiversUsername .= ", $username";
                }
                $message->setReceiverUsername($receiversUsername);
                $em->persist($userMessage);
                $em->persist($message);
            }

            $em->flush();
            $form = $this->createForm(new MessageType());

            return $this->render(
                'ClarolineCoreBundle:Message:message_form.html.twig',
                array('form' => $form->createView())
            );
        } else {

            return $this->render(
                'ClarolineCoreBundle:Message:message_form.html.twig', array('form' => $form->createView())
            );
        }
    }

    /**
     * @Route(
     *     "/list/received",
     *     name="claro_message_list_received_layout"
     * )
     *
     * Displays the layout of the received message list.
     *
     * @return Response
     */
    public function listReceivedLayoutAction()
    {
        return $this->render('ClarolineCoreBundle:Message:list_received_layout.html.twig');
    }

    /**
     * @Route(
     *     "/list/sent",
     *     name="claro_message_list_sent_layout"
     * )
     *
     * Displays the layout of the sent message list.
     *
     * @return Response
     */
    public function listSentLayoutAction()
    {
        return $this->render('ClarolineCoreBundle:Message:list_sent_layout.html.twig');
    }

    /**
     * @Route(
     *     "/list/received/{offset}",
     *     name="claro_message_list_received",
     *     options={"expose"=true}
     * )
     *
     * Displays a partial list of received message for the current user.
     * This method is called with ajax and append the result in the layout.
     *
     * @param offset the offset
     *
     * @return Response
     */
    public function listReceivedAction($offset)
    {
        $user = $this->get('security.context')->getToken()->getUser();
        $em = $this->get('doctrine.orm.entity_manager');
        $userMessages = $em->getRepository('ClarolineCoreBundle:Message')
            ->findReceivedByUser($user, false, $offset, self::MESSAGE_PER_PAGE);

        return $this->render(
            'ClarolineCoreBundle:Message:list_user_message.html.twig',
            array('userMessages' => $userMessages)
        );
    }

    /**
     * @Route(
     *     "/list/sent/{offset}",
     *     name="claro_message_list_sent",
     *     options={"expose"=true}
     * )
     *
     * Displays a partial list of sent message for the current user.
     * This method is called with ajax and append the result in the layout.
     *
     * @param offset the offset
     *
     * @return Response
     */
    public function listSentAction($offset)
    {
        $user = $this->get('security.context')->getToken()->getUser();
        $em = $this->get('doctrine.orm.entity_manager');
        $userMessages = $em->getRepository('ClarolineCoreBundle:Message')
            ->findSentByUser($user, false, $offset, self::MESSAGE_PER_PAGE);

        return $this->render(
            'ClarolineCoreBundle:Message:list_message.html.twig',
            array('userMessages' => $userMessages)
        );
    }

    /**
     * @Route(
     *     "/list/removed",
     *     name="claro_message_list_removed_layout"
     * )
     *
     * Displays the layout of the removed message list.
     *
     * @return Response
     */
    public function listRemovedLayoutAction()
    {
        return $this->render(
            'ClarolineCoreBundle:Message:list_removed_layout.html.twig'
        );
    }

    /**
     * @Route(
     *     "/show/{messageId}",
     *     name="claro_message_show"
     * )
     *
     * Displays a message.
     *
     * @param integer $messageId the message id
     *
     * @return Response
     */
    public function showAction($messageId)
    {
        $user = $this->get('security.context')->getToken()->getUser();
        $em = $this->get('doctrine.orm.entity_manager');
        $msgRepo = $em->getRepository('ClarolineCoreBundle:Message');
        $message = $msgRepo->find($messageId);
        $userMessage = $em->getRepository('ClarolineCoreBundle:UserMessage')
            ->findOneBy(array('message' => $message, 'user' => $user));
        $ancestors = $msgRepo->findAncestors($message);

        if ($userMessage != null) {
            //was received by the current user
            $username = $userMessage->getMessage()->getUser()->getUsername();
            $form = $this->createForm(new MessageType($username, 'Re: ' . $message->getObject(), true));
            $userMessage->markAsRead();
            $em->persist($userMessage);
            $em->flush();
        } else {
            //was sent by the current user
            $userMessages = $message->getUserMessages();
            $stringUsername = '';

            foreach ($userMessages as $userMessage) {
                $stringUsername .= "{$userMessage->getUser()->getUsername()}; ";
            }

            $form = $this->createForm(new MessageType($stringUsername, 'Re: ' . $message->getObject()));
        }

        return $this->render(
            'ClarolineCoreBundle:Message:show.html.twig',
            array(
                'ancestors' => $ancestors,
                'message' => $message,
                'form' => $form->createView()
            )
        );
    }

    /**
     * @Route(
     *     "/delete/from",
     *     name="claro_message_delete_from",
     *     options={"expose"=true}
     * )
     *
     * Deletes a message from the sent message list (soft delete).
     * It takes an array of ids in the query string (ids[]=1&ids[]=2).
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteFromUserAction()
    {
        $params = $this->get('request')->query->all();

        if (isset($params['ids'])) {
            $em = $this->get('doctrine.orm.entity_manager');
            foreach ($params['ids'] as $id) {
                $message = $em->getRepository('ClarolineCoreBundle:UserMessage')
                    ->find($id);

                if (!is_null($message)) {
                    $message->markAsRemoved();
                    $em->persist($message);
                }
            }
            $em->flush();
        }

        return new Response('success', 204);
    }

    /**
     * @Route(
     *     "/delete/to",
     *     name="claro_message_delete_to",
     *     options={"expose"=true}
     * )
     *
     * Deletes a message from the received message list (soft delete).
     * It takes an array of ids in the query string (ids[]=1&ids[]=2).
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteToUserAction()
    {
        $params = $this->get('request')->query->all();

        if (isset($params['ids'])) {
            $em = $this->get('doctrine.orm.entity_manager');

            foreach ($params['ids'] as $id) {
                $userMessage = $em->getRepository('ClarolineCoreBundle:UserMessage')
                    ->find($id);
                if (!is_null($userMessage)) {
                    $userMessage->markAsRemoved();
                    $em->persist($userMessage);
                }
            }
            $em->flush();
        }

        return new Response('success', 204);
    }

    /**
     * @Route(
     *     "/delete/trash",
     *     name="claro_message_delete_trash",
     *     options={"expose"=true}
     * )
     * @Method("DELETE")
     *
     * Deletes a message from trash (permanent delete).
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteTrashAction()
    {
        $params = $this->get('request')->query->all();

        if (isset($params['ids'])) {
            $em = $this->get('doctrine.orm.entity_manager');

            foreach ($params['ids'] as $id) {
                $userMessage = $em->getRepository('ClarolineCoreBundle:UserMessage')
                    ->find($id);
                $em->remove($userMessage);
            }
            $em->flush();
        }

        return new Response('success', 204);
    }

    /**
     * @Route(
     *     "/restore",
     *     name="claro_message_restore_from_trash",
     *     options={"expose"=true}
     * )
     *
     * Restore a message from the trash.
     * It takes an array of ids in the query string (ids[]=1&ids[]=2).
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function restoreFromTrashAction()
    {
        $params = $this->get('request')->query->all();

        if (isset($params['ids'])) {
            $em = $this->get('doctrine.orm.entity_manager');

            foreach ($params['ids'] as $id) {
                $userMessage = $em->getRepository('ClarolineCoreBundle:UserMessage')
                    ->find($id);
                $userMessage->markAsUnremoved();
                $em->persist($userMessage);
            }
            $em->flush();
        }

        return new Response('success', 204);
    }

    /**
     * @Route(
     *     "/list/received/search/{search}/offset/{offset}",
     *     name="claro_message_list_received_search",
     *     options={"expose"=true}
     * )
     *
     * Displays a partial list of received message for the current user.
     * This method is called with ajax and append the result in the layout.
     *
     * @param string  $search the search string (will search the object or the username)
     * @param integer $offset the offset
     *
     * @return Response
     */
    public function listSearchReceivedAction($search, $offset)
    {
        $user = $this->get('security.context')->getToken()->getUser();
        $em = $this->get('doctrine.orm.entity_manager');
        $userMessages = $em->getRepository('ClarolineCoreBundle:Message')
            ->findReceivedByUserAndObjectAndUsername($user, $search, false, $offset, self::MESSAGE_PER_PAGE);

        return $this->render(
            'ClarolineCoreBundle:Message:list_user_message.html.twig',
            array('userMessages' => $userMessages)
        );
    }

    /**
     * @Route(
     *     "/list/sent/search/{search}/offset/{offset}",
     *     name="claro_message_list_sent_search",
     *     options={"expose"=true}
     * )
     *
     * Displays a partial list of sent message for the current user.
     * This method is called with ajax and append the result in the layout.
     *
     * @param string  $search the search string (will search the object or the username)
     * @param integer $offset the offset
     *
     * @return Response
     */
    public function listSearchSentAction($search, $offset)
    {
        $user = $this->get('security.context')->getToken()->getUser();
        $em = $this->get('doctrine.orm.entity_manager');
        $userMessages = $em->getRepository('ClarolineCoreBundle:Message')
            ->findSentByUserAndObjectAndUsername($user, $search, false, $offset, self::MESSAGE_PER_PAGE);

        return $this->render(
            'ClarolineCoreBundle:Message:list_message.html.twig',
            array('userMessages' => $userMessages)
        );
    }

    /**
     * @Route(
     *     "/list/removed/{offset}",
     *     name="claro_message_list_removed",
     *     options={"expose"=true}
     * )
     *
     * Displays a partial list of removed message for the current user.
     * This method is called with ajax and append the result in the layout.
     *
     * @param offset the offset
     *
     * @return Response
     */
    public function listRemovedAction($offset)
    {
        $user = $this->get('security.context')->getToken()->getUser();
        $em = $this->get('doctrine.orm.entity_manager');
        $userMessages = $em->getRepository('ClarolineCoreBundle:Message')
            ->findRemovedByUser($user, $offset, self::MESSAGE_PER_PAGE);

        return $this->render(
            'ClarolineCoreBundle:Message:list_user_removed_message.html.twig',
            array('userMessages' => $userMessages)
        );
    }

    /**
     * @Route(
     *     "/list/removed/search/{search}/offset/{offset}",
     *     name="claro_message_list_removed_search",
     *     options={"expose"=true}
     * )
     *
     * Displays a partial list of removed message for the current user.
     * This method is called with ajax and append the result in the layout.
     *
     * @param string  $search the search string (will search the object or the username)
     * @param integer $offset the offset
     *
     * @return Response
     */
    public function listRemovedSearchAction($search, $offset)
    {
        $user = $this->get('security.context')->getToken()->getUser();
        $em = $this->get('doctrine.orm.entity_manager');
        $userMessages = $em->getRepository('ClarolineCoreBundle:Message')
            ->findRemovedByUserAndObjectAndUsername($user, $search, $offset, self::MESSAGE_PER_PAGE);

        return $this->render(
            'ClarolineCoreBundle:Message:list_user_removed_message.html.twig',
            array('userMessages' => $userMessages)
        );
    }

    /**
     * @Route(
     *     "/mark_as_read/{userMessageId}",
     *     name="claro_message_mark_as_read",
     *     options={"expose"=true}
     * )
     *
     * Marks a message as read.
     *
     * @param integer $userMessageId the userMessage id (when you send a message,
     * a UserMessage is created for every user the message was sent. It contains
     * a few attributes including the "asRead" one.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function markAsReadAction($userMessageId)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $userMessage = $em->getRepository('ClarolineCoreBundle:UserMessage')
            ->find($userMessageId);
        $userMessage->markAsRead();
        $em->persist($userMessage);
        $em->flush();

        return new Response('success', 203);
    }
}