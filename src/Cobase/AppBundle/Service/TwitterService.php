<?php

namespace Cobase\AppBundle\Service;

use Symfony\Component\DependencyInjection\ContainerInterface;
use TwitterAPIExchange;

class TwitterService
{
    /**
     * @var
     */
    protected $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @param array $hashKeys
     * @return array
     */
    public function getTweetsByHashKeys(Array $hashKeys, Array $settings = null)
    {
        if ($this->container->getParameter('twitter_enabled') === false) {
            return array();
        }

        if ($settings === null) {
            $settings = $this->getDefaultSettings();
        }

        $tweets = $this->getTweets($hashKeys, $settings);

        $tweetsArray = $this->processTweetList($tweets);

        return $tweetsArray;
    }

    /**
     * @return array
     */
    private function getDefaultSettings()
    {
        $settings = array(
            'oauth_access_token' => $this->container->getParameter('twitter_oauth_access_token'),
            'oauth_access_token_secret' => $this->container->getParameter('twitter_oauth_access_token_secret'),
            'consumer_key' => $this->container->getParameter('twitter_consumer_key'),
            'consumer_secret' => $this->container->getParameter('twitter_consumer_secret')
        );

        return $settings;
    }

    /**
     * @param array $hashKeys
     * @param array $settings
     * @return string
     */
    private function getTweets(Array $hashKeys, Array $settings)
    {
        $hashQuery = implode('+OR+', $hashKeys);

        $url = 'https://api.twitter.com/1.1/search/tweets.json';
        $requestMethod = 'GET';
        $getfield = '?q=' . $hashQuery . '&result_type=recent';

        $twitter = new TwitterAPIExchange($settings);
        $tweets = $twitter->setGetfield($getfield)
            ->buildOauth($url, $requestMethod)
            ->performRequest();

        return $tweets;
    }

    /**
     * @param $tweets
     * @return array
     */
    private function processTweetList($tweets)
    {
        $tweetList = array();
        $tweetArray = json_decode($tweets);

        if ($tweetArray) {
            foreach($tweetArray->statuses as $tweet) {
                $tweetList[] = $tweet->text;
            }
        }

        return $tweetList;
    }
}
