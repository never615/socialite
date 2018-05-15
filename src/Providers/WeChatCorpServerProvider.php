<?php

/*
 * This file is part of the overtrue/socialite.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Overtrue\Socialite\Providers;

use Mallto\Tool\Exception\ResourceException;
use Overtrue\Socialite\AccessTokenInterface;
use Overtrue\Socialite\AuthorizeFailedException;
use Overtrue\Socialite\InvalidStateException;
use Overtrue\Socialite\User;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class WeChatCorpServerProvider.
 *
 * @link http://qydev.weixin.qq.com/wiki/index.php?title=OAuth%E9%AA%8C%E8%AF%81%E6%8E%A5%E5%8F%A3[WeChat - 企业号 OAuth 文档]
 */
class WeChatCorpServerProvider extends WeChatProvider
{
    /**
     * @var \EasyWeChat\CorpServer\Core\AuthorizerAccessToken
     */
    protected $accessToken;

    /**
     * {@inheritdoc}.
     */
    protected $scopes = ['snsapi_base'];

    /**
     * agent id
     */
    protected $agentId;

    /**
     * Create a new provider instance.
     * (Overriding).
     *
     * @param \Symfony\Component\HttpFoundation\Request         $request
     * @param string                                            $clientId
     * @param \EasyWeChat\CorpServer\Core\AuthorizerAccessToken $authorizerAccessToken
     * @param string|null                                       $redirectUrl
     */
    public function __construct(Request $request, $clientId, $authorizerAccessToken, $redirectUrl = null)
    {
        parent::__construct($request, $clientId, null, $redirectUrl);

        $this->accessToken = $authorizerAccessToken;
    }

    /**
     * Set the scopes of the requested access.
     *
     * @param $agentId
     * @return $this
     *
     */
    public function agent($agentId)
    {
        $this->agentId = $agentId;

        return $this;
    }

    /**
     * {@inheritdoc}.
     */
    public function getCodeFields($state = null)
    {
        $this->with(['agentid' => $this->getAgentId()]);

        return parent::getCodeFields($state);
    }

    /**
     * {@inheritdoc}.
     */
    protected function getTicketUrl()
    {
        return 'https://qyapi.weixin.qq.com/cgi-bin/user/getuserinfo';
    }

    /**
     * {@inheritdoc}
     */
    public function user(AccessTokenInterface $token = null)
    {
        if (is_null($token) && $this->hasInvalidState()) {
            throw new InvalidStateException();
        }

        $userTicket = $this->getUserTicket($this->getCode());

        $user = $this->getUserByTicket($userTicket);

        $user = $this->mapUserToObject($user)->merge(['original' => $user]);

        return $user;
    }

    protected function mapUserToObject(array $user)
    {
        return new User([
            'id'       => $this->arrayItem($user, 'userid'),
            'name'     => $this->arrayItem($user, 'name'),
            'nickname' => $this->arrayItem($user, 'name'),
            'avatar'   => $this->arrayItem($user, 'avatar'),
            'email'    => null,
        ]);
    }

    /**
     * {@inheritdoc}.
     */
    protected function getUserByTicket($userTicket)
    {
        $token = $this->getToken();
        $url = "https://qyapi.weixin.qq.com/cgi-bin/user/getuserdetail?access_token=$token";

        $response = $this->getHttpClient()->post($url, [
            'json' => ['user_ticket' => $userTicket],
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * {@inheritdoc}.
     */
    public function getUserTicket($code)
    {
        $response = $this->getHttpClient()->get($this->getTicketUrl(), [
            'headers' => ['Accept' => 'application/json'],
            'query'   => $this->getTicketFields($code),
        ]);

        return $this->parseUserTicket($response->getBody());
    }


    /**
     * Get the access token from the token response body.
     *
     * @param \Psr\Http\Message\StreamInterface|array $body
     *
     * @return \Overtrue\Socialite\AccessToken
     */
    protected function parseUserTicket($body)
    {
        if (!is_array($body)) {
            $body = json_decode($body, true);
        }

        if (empty($body['user_ticket'])) {
            //todo 处理非企业号成员
            \Log::warning("非企业号成员请求授权");
            \Log::warning($body);
            throw new ResourceException("微信授权失败:非企业号成员请求授权");
//            throw new AuthorizeFailedException('企业号授权失败', null);
//            throw new AuthorizeFailedException('Authorize Failed: '.json_encode($body, JSON_UNESCAPED_UNICODE), $body);
        }

        return $body['user_ticket'];
    }

    /**
     * {@inheritdoc}.
     */
    protected function getTicketFields($code)
    {
        return [
            'access_token' => $this->getToken(),
            'code'         => $code,
        ];
    }

    /**
     * Get component app id.
     *
     * @return string
     */
    protected function getAgentId()
    {
        return $this->agentId;
    }

    /**
     * Get component access token.
     *
     * @return string
     */
    protected function getToken()
    {
        return $this->accessToken->getToken();
    }
}
