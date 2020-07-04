<?php
/**
 * @author        Tharanga Kothalawala <tharanga.kothalawala@hubculture.com>
 * @copyright (c) 2020 by HubCulture Ltd.
 */

namespace Hub\HubAPI\Service;

use Hub\HubAPI\Auth\RedirectingLoginHelper;
use Hub\HubAPI\HubClient;
use Hub\HubAPI\Service\Exception\HubIdApiException;
use Hub\HubAPI\Service\Model\File;
use InvalidArgumentException;

/**
 * This refreshes any expired token during a token expired API error and re-fires the same request returning the
 * response.
 *
 * @package Hub\HubAPI\Service
 */
class TokenRefreshingService extends Service
{
    const API_TOKEN_EXPIRED_ERROR_MESSAGE = '{"error":"token_expired"}';

    /**
     * @inheritdoc
     */
    protected function get($api, array $params = array())
    {
        try {
            return parent::get($api, $params);
        } catch (HubIdApiException $ex) {
            if ($this->handleHubIdApiException($ex)) {
                return parent::get($api, $params);
            }
            throw $ex;
        }
    }

    /**
     * @inheritdoc
     */
    protected function put($api, array $params = array())
    {
        try {
            return parent::put($api, $params);
        } catch (HubIdApiException $ex) {
            if ($this->handleHubIdApiException($ex)) {
                return parent::put($api, $params);
            }
            throw $ex;
        }
    }

    /**
     * @inheritdoc
     */
    protected function postJson($api, array $params = array())
    {
        try {
            return parent::postJson($api, $params);
        } catch (HubIdApiException $ex) {
            if ($this->handleHubIdApiException($ex)) {
                return parent::postJson($api, $params);
            }
            throw $ex;
        }
    }

    /**
     * @inheritdoc
     */
    protected function postFormData($api, array $params = array())
    {
        try {
            return parent::postFormData($api, $params);
        } catch (HubIdApiException $ex) {
            if ($this->handleHubIdApiException($ex)) {
                return parent::postFormData($api, $params);
            }
            throw $ex;
        }
    }

    /**
     * @inheritdoc
     */
    protected function uploadFile($api, File $file, array $params = array())
    {
        try {
            return parent::uploadFile($api, $file, $params);
        } catch (HubIdApiException $ex) {
            if ($this->handleHubIdApiException($ex)) {
                return parent::uploadFile($api, $file, $params);
            }
            throw $ex;
        }
    }

    /**
     * @inheritdoc
     */
    protected function delete($api, array $params = array())
    {
        try {
            return parent::delete($api, $params);
        } catch (HubIdApiException $ex) {
            if ($this->handleHubIdApiException($ex)) {
                return parent::delete($api, $params);
            }
            throw $ex;
        }
    }

    /**
     * @inheritdoc
     */
    protected function requestWithJson($api, $method = 'get', array $params = array())
    {
        try {
            return parent::requestWithJson($api, $method, $params);
        } catch (HubIdApiException $ex) {
            if ($this->handleHubIdApiException($ex)) {
                return parent::requestWithJson($api, $method, $params);
            }
            throw $ex;
        }
    }

    /**
     * @inheritdoc
     */
    protected function requestWithForm($api, $method = 'get', array $params = array())
    {
        try {
            return parent::requestWithForm($api, $method, $params);
        } catch (HubIdApiException $ex) {
            if ($this->handleHubIdApiException($ex)) {
                return parent::requestWithForm($api, $method, $params);
            }
            throw $ex;
        }
    }

    /**
     * This checks if the given hub id exception is about a token expiry and refreshes the token if so and return true.
     *
     * @param HubIdApiException $exception The exception caught to be handled
     *
     * @return bool true if token refreshed or false otherwise
     */
    private function handleHubIdApiException(HubIdApiException $exception)
    {
        if ($exception->getMessage() !== self::API_TOKEN_EXPIRED_ERROR_MESSAGE) {
            return false;
        }

        $this->log(sprintf(
            "access_token has expired. Sending a refresh token request for API call : '%s'",
            $exception->getCalledApi()
        ));

        $accessToken = $this->getAccessToken();
        if (!empty($accessToken)) {
            $this->setAccessToken($this->getRefreshToken($accessToken));
            return true;
        }

        return false;
    }

    /**
     * Use this to get a new refresh token if the current one is not expired.
     *
     * @param string $accessToken The currently used access token to be attempted to refresh
     *
     * @return string
     */
    private function getRefreshToken($accessToken)
    {
        $tokenParts = explode(".", $accessToken);
        if (!isset($tokenParts[1])) {
            return $accessToken;
        }

        $tokenPayload = base64_decode($tokenParts[1]);
        $jwtPayload = @json_decode($tokenPayload, true);
        if (empty($jwtPayload['exp'])) {
            return $accessToken;
        }

        if (intval($jwtPayload['exp']) <= time()) {
            try {
                $refreshToken = $this->getRedirectingLoginHelper()->getRefreshToken($accessToken);
                $this->log("refreshing the access token as it was expired at " . $jwtPayload['exp']);
            } catch (InvalidArgumentException $ex) {
                return $accessToken;
            } catch (HubIdApiException $ex) {
                return $accessToken;
            }

            if (!empty($refreshToken)) {
                return $refreshToken;
            }
        }

        return $accessToken;
    }

    /**
     * @return RedirectingLoginHelper
     */
    private function getRedirectingLoginHelper()
    {
        return new RedirectingLoginHelper($this->config);
    }
}
