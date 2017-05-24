<?php
/**
 * @author      Alex Bilbie <hello@alexbilbie.com>
 * @copyright   Copyright (c) Alex Bilbie
 * @license     http://mit-license.org/
 *
 * @link        https://github.com/thephpleague/oauth2-server
 */
namespace DwComment\Components\Oauth2\Repositories;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use DwComment\Components\Oauth2\Entities\AccessTokenEntity;
use DwComment\Models\AccessTokens;
use DwComment\Models\RefreshTokens;

class AccessTokenRepository implements AccessTokenRepositoryInterface
{

    /**
     *
     * @author Frank
     *         function point description
     *         befor access_token inset AccessToken Table
     *         after The data save Redis
     */
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity)
    {
        $access_token_module = new AccessTokens();
        // insert access_token table
        $access_token_module->userId = $accessTokenEntity->getUserIdentifier();
        $access_token_module->tokenId = $accessTokenEntity->getIdentifier();
        $d = new \DateTime($accessTokenEntity->getExpiryDateTime()->date);
        $access_token_module->expiry = time($d->format('Y-m-d H:i:s'));
        // insert refresh_tokens
        $refresh_token_module = new RefreshTokens();
        $refresh_token_module->userId = $accessTokenEntity->getUserIdentifier();
        $refresh_token_module->tokenId = $accessTokenEntity->getIdentifier();
        $d = new \DateTime($accessTokenEntity->getExpiryDateTime()->date);
        $refresh_token_module->expiry = time($d->format('Y-m-d H:i:s'));
        try {
            $_insert_access_token_ret = $access_token_module->save();
            $_insert_refresh_token_ret = $refresh_token_module->save();
            if (! $_insert_access_token_ret && ! $_insert_refresh_token_ret) {
                throw new \DwComment\Exceptions\HttpException('Unauthorized', 401, false, [
                    'dev' => 'The bearer token Insert access_tokens error',
                    'internalCode' => 'P1890',
                    'more' => ''
                ]);
            }
        } catch (Exception $e) {}
        // return true;
    }

    /**
     * @ERROR!!!
     */
    public function revokeAccessToken($tokenId)
    {
        // Some logic here to revoke the access token
    }

    /**
     * @ERROR!!!
     */
    public function isAccessTokenRevoked($tokenId)
    {
        return false; // Access token hasn't been revoked
    }

    /**
     * @ERROR!!!
     */
    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, $userIdentifier = null)
    {
        $accessToken = new AccessTokenEntity();
        $accessToken->setClient($clientEntity);
        foreach ($scopes as $scope) {
            $accessToken->addScope($scope);
        }
        $accessToken->setUserIdentifier($userIdentifier);
        return $accessToken;
    }
}