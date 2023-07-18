<?php

namespace Stevenmaguire\OAuth2\Client\Provider;

use Exception;
use InvalidArgumentException;
use League\OAuth2\Client\Grant\AbstractGrant;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

class Salesforce extends AbstractProvider
{
    use BearerAuthorizationTrait;

    /**
     * @var string Key used in a token response to identify the resource owner.
     */
    const ACCESS_TOKEN_RESOURCE_OWNER_ID = 'id';

    /**
     * Base domain used for authentication
     *
     * @var string
     */
    protected $domain = 'https://login.salesforce.com';

    /**
     * Get authorization url to begin OAuth flow
     *
     * @return string
     */
    public function getBaseAuthorizationUrl()
    {
        return $this->domain . '/services/oauth2/authorize';
    }

    /**
     * Get access token url to retrieve token
     *
     * @return string
     */
    public function getBaseAccessTokenUrl(array $params)
    {
        return $this->domain . '/services/oauth2/token';
    }

    /**
     * Get logout URL
     *
     * @return string
     */
    public function getLogoutUrl(array $params)
    {
        //https://testlogin.messe-muenchen.de/users/apex/IdentityLogout?retUrl=[gewünschteRedirectURLfürUser]
        $postfix = '';
        if (isset($params['redirect_uri']) && filter_var($params['redirect_uri'], FILTER_VALIDATE_URL)) {
            $postfix = sprintf('?retUrl=%s', urlencode($params['redirect_uri']));
        }
        return $this->domain . '/apex/IdentityLogout' . $postfix;
    }

    /**
     * @param array $params
     * @return string
     */
    public function getPreAuthUrl(array $params)
    {
        $postfix = '';
        foreach ($params as $key => $value) {
            if (($key === 'redirect_uri' || $key === 'cookie_redirect_uri') && !filter_var($value, FILTER_VALIDATE_URL)) {
                continue;
            }
            if ($key === 'redirect_uri') {
                $postfix .= sprintf('retUrl=%s&', urlencode($value));
            }
            if ($key === 'cookie_redirect_uri') {
                $postfix .= sprintf('cRetUrl=%s&', urlencode($value));
            }
            if ($key === 'locale') {
                $postfix .= sprintf('locale=%s&', $value);
            }
        }

        if (!empty($postfix)) {
            $postfix = rtrim($postfix, '& ');
            $postfix = '?' . $postfix;
        }
        return $this->domain . '/IdentityCookie' . $postfix;
    }

    /**
     * Get provider url to fetch user details
     *
     * @param  AccessToken $token
     *
     * @return string
     */
    public function getResourceOwnerDetailsUrl(AccessToken $token)
    {
        return $token->getResourceOwnerId();
    }

    /**
     * Get the default scopes used by this provider.
     *
     * This should not be a complete list of all scopes, but the minimum
     * required for the provider user interface!
     *
     * @return array
     */
    protected function getDefaultScopes()
    {
        return [];
    }

    /**
     * Retrives the currently configured provider domain.
     *
     * @return string
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * Returns the string that should be used to separate scopes when building
     * the URL for requesting an access token.
     *
     * @return string Scope separator, defaults to ','
     */
    protected function getScopeSeparator()
    {
        return ' ';
    }

    /**
     * Check a provider response for errors.
     *
     * @throws IdentityProviderException
     * @param  ResponseInterface $response
     * @param  string $data Parsed response data
     * @return void
     */
    protected function checkResponse(ResponseInterface $response, $data)
    {
        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            throw new IdentityProviderException(
                isset($data[0]['message']) ? $data[0]['message'] : $response->getReasonPhrase(),
                $statusCode,
                $response
            );
        }
    }

    /**
     * Generate a user object from a successful user details request.
     *
     * @param object $response
     * @param AccessToken $token
     * @return League\OAuth2\Client\Provider\ResourceOwnerInterface
     */
    protected function createResourceOwner(array $response, AccessToken $token)
    {
        return new SalesforceResourceOwner($response);
    }

    /**
     * Creates an access token from a response.
     *
     * The grant that was used to fetch the response can be used to provide
     * additional context.
     *
     * @param  array $response
     * @param  AbstractGrant $grant
     * @return AccessToken
     */
    protected function createAccessToken(array $response, AbstractGrant $grant)
    {
        return new \Stevenmaguire\OAuth2\Client\Token\AccessToken($response);
    }

    /**
     * Updates the provider domain with a given value.
     *
     * @throws  InvalidArgumentException
     * @param string $domain
     * @return  Salesforce
     */
    public function setDomain($domain)
    {
        try {
            $this->domain = (string) $domain;
        } catch (Exception $e) {
            throw new InvalidArgumentException(
                'Value provided as domain is not a string'
            );
        }

        return $this;
    }
}
