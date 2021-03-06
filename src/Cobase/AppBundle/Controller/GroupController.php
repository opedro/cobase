<?php

namespace Cobase\AppBundle\Controller;

use Cobase\AppBundle\Controller\BaseController;
use Cobase\AppBundle\Entity\Group;
use Cobase\AppBundle\Form\GroupType;
use Cobase\AppBundle\Entity\Post;
use Cobase\AppBundle\Form\PostType;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Symfony\Component\Security\Acl\Permission\MaskBuilder;

/**
 * Group controller.
 */
class GroupController extends BaseController
{
    /**
     * Render a new group form
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function newAction()
    {
        $group = new Group();
        $user  = $this->getCurrentUser();
        $form = $this->createForm(new GroupType(), $group);

        $gravatarGiven = true;
        if ($user->getGravatar() == null) {
            // @TODO: Implement this feature
            $gravatarGiven = false;
            $gravatarGiven = false;
        }

        return $this->render('CobaseAppBundle:Group:create.html.twig',
            $this->mergeVariables(
                array(
                    'form'     => $form->createView(),
                    'gravatar' => $gravatarGiven,
                )
            )
        );
    }

    /**
     * Create new group
     *
     * This function handles the new group's form data and creates new group
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function createAction()
    {
        $group   = new Group();
        $form    = $this->createForm(new GroupType(), $group);
        $user    = $this->getCurrentUser();
        $service = $this->getGroupService();

        if ($this->getRequest()->getMethod() != 'POST') {
            return $this->redirect($this->generateUrl('CobaseAppBundle_group_new'));
        }

        if (!$this->processForm($form)) {
            return $this->render('CobaseAppBundle:Group:create.html.twig',
                $this->mergeVariables(
                    array(
                        'form'     => $form->createView(),
                    )
                )
            );
        }

        $groupUrl = $this->container->get('router')
            ->generate('CobaseAppBundle_group_view', array('groupId' => $group->getShortUrl()), true);

        // Create group
        $service->saveGroup($group, $user);

        // creating the ACL
        $aclProvider = $this->get('security.acl.provider');
        $objectIdentity = ObjectIdentity::fromDomainObject($group);
        $acl = $aclProvider->createAcl($objectIdentity);

        // retrieving the security identity of the currently logged-in user
        $securityIdentity = UserSecurityIdentity::fromAccount($user);

        // grant owner access
        $acl->insertObjectAce($securityIdentity, MaskBuilder::MASK_OWNER);
        $aclProvider->updateAcl($acl);

        $message = sprintf(
            'Your new group "%s" has been created.<br/>Permalink: <a href="%s">%s</a>',
            $group->getTitle(),
            $groupUrl,
            $groupUrl
        );

        $this->get('session')->getFlashBag()->add(
            'group.message', $message
        );

        return $this->redirect($this->generateUrl('CobaseAppBundle_all_groups'));
    }

    /**
     * View Group
     *
     * @param $shortUrl
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function viewAction($groupId)
    {
        if ($redirect = $this->checkLoginRequirement('CobaseAppBundle_all_groups', false)) {
            return $redirect;
        }

        $post = new Post();

        $groupService = $this->getGroupService();
        $postService = $this->getPostService();
        $subscriptionService = $this->getSubscriptionService();
        $notificationService = $this->getNotificationService();

        $request    = $this->getRequest();
        $user       = $this->getCurrentUser();
        $form       = $this->createForm(new PostType(), $post);
        $group      = $groupService->getGroupById($groupId);

        if (!$group) {
            return $this->render('CobaseAppBundle:Group:notfound.html.twig',
                $this->mergeVariables()
            );
        }

        $groups     = $groupService->getAllPublicGroups(null, 'b.title', 'ASC');
        $postsQuery  = $postService->getLatestPublicPostsForGroupQuery($group);

        $processedTags = $this->processTags($group->getTags());
        $groupTweets = $this->getGroupTweets($group, $processedTags);

        $paginator  = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $postsQuery,
            $this->get('request')->query->get('page', 1) /*page number*/,
            $this->container->getParameter('posts_per_page') /*limit per page*/
        );

        $isSubscribed = false;
        $isNotified = false;
        if ($user) {
            $isSubscribed   = $subscriptionService->hasUserSubscribedToGroup($group, $user);
            $isNotified     = $notificationService->isUserUserNotifiedOfNewGroupPosts($group, $user);
        }

        if ($request->getMethod() == 'POST') {
            if ($this->processForm($form)) {

                // Convert line breaks to BR tag
                $content = $post->getContent();
                $content = str_replace("\n", '<br/>', $content);

                $post->setContent($content);
                $post->setGroup($group);
                $post->setUser($user);

                $postService->savePost($post);

                $this->getNotificationService()->newPostAdded($post);

                // creating the ACL
                $aclProvider = $this->get('security.acl.provider');
                $objectIdentity = ObjectIdentity::fromDomainObject($post);
                $acl = $aclProvider->createAcl($objectIdentity);

                // retrieving the security identity of the currently logged-in user
                $securityIdentity = UserSecurityIdentity::fromAccount($user);

                // grant owner access
                $acl->insertObjectAce($securityIdentity, MaskBuilder::MASK_OWNER);
                $aclProvider->updateAcl($acl);

                $this->get('session')->getFlashBag()->add('group.message', 'Your post has been sent, thank you!');

                return $this->redirect($this->generateUrl('CobaseAppBundle_group_view',
               	    array(
                        'groupId'    => $groupId,
                    )
                ));
            }
        }

        $this->get('cobase_app.service.comment')->initializeCommentThreads($pagination->getItems());

        return $this->render('CobaseAppBundle:Group:view.html.twig',
            $this->mergeVariables(
                array(

                    'group'                 => $group,
                    'groups'                => $groups,
                    'pagination'            => $pagination,
                    'form'                  => $form->createView(),
                    'subscribed'            => $isSubscribed,
                    'notified'              => $isNotified,
                    'groupTweets'           => $groupTweets,
                    'groupTweetHashTags'    => $processedTags
                )
            )
        );
    }

    /**
     * Modify Group
     *
     * @param $groupId
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function modifyAction($groupId)
    {
        $groupService    = $this->getGroupService();
        $postService = $this->getPostService();

        $group    = $groupService->getGroupById($groupId);
        $request  = $this->getRequest();
        $user     = $this->getCurrentUser();

        if (!$group) {
            return $this->render('CobaseAppBundle:Group:notfound.html.twig',
                $this->mergeVariables()
            );
        }

        if ($group->getUser() !== $user && !$this->get('security.context')->isGranted('ROLE_ADMIN') ) {
            return $this->render('CobaseAppBundle:Group:noaccess.html.twig',
                $this->mergeVariables()
            );
        }

        $form = $this->createForm(new GroupType(), $group);

        if ($request->getMethod() == 'POST') {
            if ($this->processForm($form)) {
                $groupService->saveGroup($group, $user);

                $this->get('session')->getFlashBag()->add(
                    'group.message',
                    'Your changes to the group have been saved.');

                return $this->redirect($this->generateUrl('CobaseAppBundle_group_view',
                    array(
                        'groupId' => $groupId,
                    )
                ));
            }
        }

        return $this->render('CobaseAppBundle:Group:modify.html.twig',
            $this->mergeVariables(
                array(
                    'group'         => $group,
                    'form'          => $form->createView(),
                )
            )
        );
    }

    /**
     * @param $postId
     */
    public function deleteAction()
    {
        $groupService = $this->getGroupService();
        $subscriptionService = $this->getSubscriptionService();

        $user = $this->getCurrentUser();
        $groupId = $this->getRequest()->get('groupId');

        $url = $this->generateUrl(
            'CobaseAppBundle_all_groups'
        );

        $group = $groupService->getGroupById($groupId);

        $group->setDeleted(new \DateTime());
        $groupService->saveGroup($group);

        $subscriptions = $subscriptionService->getSubscriptionsForGroup($group);
        foreach ($subscriptions as $subscription) {
            $subscription->setDeleted(new \DateTime());
            $subscriptionService->updateSubscription($subscription);
        }

        $this->get('session')->getFlashBag()->add('group.message', 'Group has been successfully deleted.');

        return new Response(
            json_encode(
                array(
                    "success" => true,
                    "url"     => $url,
                )
            )
        );
    }

    /**
     * Subscribe a user to a group
     *
     * @param $groupId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function subscribeAction($groupId)
    {
        $groupService = $this->getGroupService();
        $subscriptionService = $this->getSubscriptionService();

        $group    = $groupService->getGroupById($groupId);
        $request  = $this->getRequest();
        $user     = $this->getCurrentUser();

        if (!$group) {
            return $this->render('CobaseAppBundle:Group:notfound.html.twig',
                $this->mergeVariables()
            );
        }

        $subscriptionService->subscribe($group, $user);

        $this->get('session')->getFlashBag()->add(
            'subscription.transaction',
            'Your subscription to group "' . $group->getTitle() . '" has been added.');

        return $this->redirect($this->generateUrl('CobaseAppBundle_homepage',
            $this->mergeVariables()
        ));
    }

    /**
     * Unsubscribe a user from a group
     *
     * @param $groupId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function unsubscribeAction($groupId)
    {
        $groupService = $this->getGroupService();
        $subscriptionService = $this->getSubscriptionService();

        $group    = $groupService->getGroupById($groupId);
        $request  = $this->getRequest();
        $user     = $this->getCurrentUser();

        if (!$group) {
            return $this->render('CobaseAppBundle:Group:notfound.html.twig',
                $this->mergeVariables()
            );
        }

        $subscriptionService->unsubscribe($group, $user);

        $this->get('session')->getFlashBag()->add(
            'subscription.transaction',
            'Your subscription to group "' . $group->getTitle() . '" has been removed.');

        return $this->redirect($this->generateUrl('CobaseAppBundle_homepage',
            $this->mergeVariables()
        ));
    }

    /**
     * Show all groups
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showAllAction($orderByType = 'b.title', $orderType = 'asc')
    {
        if ($redirect = $this->checkLoginRequirement('CobaseAppBundle_all_groups', false)) {
            return $redirect;
        }

        $orderBy = 'b.title';
        if ($orderByType == 'created') {
            $orderBy = 'b.created';
        }

        $order = 'asc';
        if ($orderType == 'desc') {
            $order = 'desc';
        }

        $limit = null;

        $groupService = $this->getGroupService();
        $groups = $groupService->getAllPublicGroups($limit, $orderBy, $order);

        $paginator  = $this->get('knp_paginator');
        $pagination = $paginator->paginate(
            $groups,
            $this->get('request')->query->get('page', 1) /*page number*/,
            $this->container->getParameter('groups_per_page') /*limit per page*/
        );

        return $this->render('CobaseAppBundle:Page:allGroups.html.twig',
            $this->mergeVariables(
                array(
                    'pagination' => $pagination,
                    'groups' => $groups
                )
            )
        );
    }

    public function feedAction($groupId)
    {
        $feed   = $this->get('eko_feed.feed.manager')->get('post');

        if (!$this->container->get('security.context')->isGranted('IS_AUTHENTICATED_REMEMBERED')) {
            if ($this->container->getParameter('login_required')) {
                return new Response($feed->render('rss'));
            }
        }

        return $this->getFeedPosts($groupId);
    }

    public function notifyAction($groupId)
    {
        $groupService = $this->getGroupService();
        $notificationService = $this->getNotificationService();

        $group    = $groupService->getGroupById($groupId);
        $user     = $this->getCurrentUser();

        if (!$group) {
            return $this->render('CobaseAppBundle:Group:notfound.html.twig',
                $this->mergeVariables()
            );
        }

        if ($notificationService->isUserUserNotifiedOfNewGroupPosts($group, $user)) {
            $this->get('session')->getFlashBag()->add(
                'group.message',
                'You are already notified when a new post is made to the group "' . $group->getTitle() . '"');
            return $this->redirect($this->generateUrl('CobaseAppBundle_group_view', array('groupId' => $groupId)));
        }

        $notificationService->setUserToBeNotifiedOfNewGroupPosts($group, $user);

        $this->get('session')->getFlashBag()->add(
            'group.message',
            'You will be notified when a new post is made to the group "' . $group->getTitle() . '"');

        return $this->redirect($this->generateUrl('CobaseAppBundle_group_view', array('groupId' => $groupId)));
    }

    public function unnotifyAction($groupId)
    {
        $groupService = $this->getGroupService();
        $notificationService = $this->getNotificationService();

        $group    = $groupService->getGroupById($groupId);
        $user     = $this->getCurrentUser();

        if (!$group) {
            return $this->render('CobaseAppBundle:Group:notfound.html.twig',
                $this->mergeVariables()
            );
        }

        if (!$notificationService->isUserUserNotifiedOfNewGroupPosts($group, $user)) {
            $this->get('session')->getFlashBag()->add(
                'group.message',
                'You have not selected to be notified of new posts in the group "' . $group->getTitle() . '"');
            return $this->redirect($this->generateUrl('CobaseAppBundle_group_view', array('groupId' => $groupId)));
        }

        $notificationService->setUserNotToBeNotifiedOfNewGroupPosts($group, $user);
        $this->get('session')->getFlashBag()->add(
            'group.message',
            'You will no longer receive notifications when new posts are posted to the group  "'
            . $group->getTitle() . '"');

        return $this->redirect($this->generateUrl('CobaseAppBundle_group_view', array('groupId' => $groupId)));
    }

    /**
     * @param int $groupId
     * @return Response
     */
    protected function getFeedPosts($groupId)
    {
        $feed   = $this->get('eko_feed.feed.manager')->get('post');

        $group  = $this->getGroupService()->getGroupById($groupId);

        if ($group) {
            $posts = $this->getPostService()->getLatestPublicPostsForGroup($group, 50);
            $feed->addFromArray($posts);
        }

        return new Response($feed->render('rss'));
    }

    /**
     * @param Group $group
     * @param array $processedTags
     * @return array
     */
    private function getGroupTweets(Group $group, array $processedTags)
    {
        $settings = $this->getTwitterApiSettings();
        $twitterService = $this->getTwitterService();

        $groupTweets = array();

        if ($group->getTags()) {
            $groupTweets = $twitterService->getTweetsByHashKeys(
                $processedTags,
                $settings
            );
        }

        return $groupTweets;
    }

    /**
     * @return array
     */
    private function getTwitterApiSettings() {
       $settings = array(
            'twitter_enabled'           => $this->container->getParameter('twitter_enabled'),
            'oauth_access_token'        => $this->container->getParameter('twitter_oauth_access_token'),
            'oauth_access_token_secret' => $this->container->getParameter('twitter_oauth_access_token_secret'),
            'consumer_key'              => $this->container->getParameter('twitter_consumer_key'),
            'consumer_secret'           => $this->container->getParameter('twitter_consumer_secret')
        );

        return $settings;
    }

    /**
     * @param string $tags
     * @return array
     */
    protected function processTags($tags)
    {
        $processedArray = array();
        $rawTags = explode(',', $tags);

        foreach($rawTags as $tag) {
            $tag = trim($tag);
            $tag = str_replace(' ', '_', $tag);
            if (substr($tag, 0, 1) !== '#') {
                $tag = '#' . $tag;
            }
            $processedArray[] = $tag;
        }

        return $processedArray;
    }
}
