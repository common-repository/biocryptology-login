<?php

namespace League\OAuth2\Client\Provider;

use League\OAuth2\Client\Exception\HostedDomainException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

class Biocryptology extends AbstractProvider {

    use BearerAuthorizationTrait;

    /** @var string $accessType */
    protected $accessType;
    /** @var string $claims */
    protected $claims;
    /** @var string $host */
    protected $host;
    /** @var string $hostedDomain */
    protected $hostedDomain;
    /** @var string $prompt */
    protected $prompt;
    /** @var array $scopes */
    protected $scopes = [];

    public function __construct(array $options = [], array $collaborators = []) {
        $this->host = BiocryptologyData::getApiHost();
        parent::__construct($options, $collaborators);
    }

    public function getBaseAuthorizationUrl() {
        return $this->host . '/V1/auth';
    }

    public function getBaseAccessTokenUrl(array $params) {
        return $this->host . '/V1/token';
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token) {
        return $this->host . '/V1/userinfo';
    }

    protected function getAuthorizationParameters(array $options) {
        if ($this->hostedDomain && empty($options['hd'])) {
            $options['hd'] = $this->hostedDomain;
        }

        if ($this->accessType && empty($options['access_type'])) {
            $options['access_type'] = $this->accessType;
        }

        if ($this->prompt && empty($options['prompt'])) {
            $options['prompt'] = $this->prompt;
        }

        // The "approval_prompt" option MUST be removed to prevent conflicts with non-empty "prompt".
        if (!empty($options['prompt'])) {
            $options['approval_prompt'] = null;
        }

        // Default scopes MUST be included for OpenID Connect.
        // Additional scopes MAY be added by constructor or option.
        $scopes = array_merge($this->getDefaultScopes(), $this->scopes);
        if (!empty($options['scope'])) {
            $scopes = array_merge($scopes, $options['scope']);
        }
        $options['scope'] = array_unique($scopes);

        return parent::getAuthorizationParameters($options);
    }

    protected function getDefaultScopes() {
        // "openid" MUST be the first scope in the list.
        return ['openid'];
    }

    protected function getScopeSeparator() {
        return ' ';
    }

    protected function checkResponse(ResponseInterface $response, $data) {
        // @codeCoverageIgnoreStart
        if (empty($data['error'])) {
            return;
        }
        // @codeCoverageIgnoreEnd

        $code  = 0;
        $error = $data['error'];
        if (is_array($error)) {
            $code  = $error['code'];
            $error = $error['message'];
        }

        throw new IdentityProviderException($error, $code, $data);
    }

    protected function createResourceOwner(array $response, AccessToken $token) {
        $user = new BiocryptologyUser($response);
        $this->assertMatchingDomain($user->getHostedDomain());

        return $user;
    }

    /**
     * @param $hostedDomain
     */
    protected function assertMatchingDomain($hostedDomain) {
        if ($this->hostedDomain === null) {
            // No hosted domain configured.
            return;
        }

        if ($this->hostedDomain === '*' && $hostedDomain) {
            // Any hosted domain is allowed.
            return;
        }

        if ($this->hostedDomain === $hostedDomain) {
            // Hosted domain is correct.
            return;
        }

        throw HostedDomainException::notMatchingDomain($this->hostedDomain);
    }
}
