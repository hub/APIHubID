<?php
/**
 * @author        Serhiy Osetskiy  <serhiy.osetskiy@hubculture.com>
 * @author        Tharanga Kothalawala <tharanga.kothalawala@hubculture.com>
 * @copyright (c) 2019 by HubCulture Ltd.
 */

namespace Hub\HubAPI;

use Hub\HubAPI\Service\Exception\HubIdApiException;
use Hub\HubAPI\Service\Service;
use Valitron\Validator;
use Hub\HubAPI\Auth\RedirectingLoginHelper;

class HubClient extends Service
{
    const COOKIE_TOKEN_NAME = 'hubid-api-client';

    /**
     * @var array
     */
    private $response;

    /**
     * @var array
     */
    private $request;

    /**
     * @var string
     */
    private static $token;

    /**
     * @return RedirectingLoginHelper
     * @throws \Exception
     */
    public function getRedirectingLoginHelper()
    {
        return new RedirectingLoginHelper($this->config);
    }

    /**
     * Create a password hash. For verification, you need to use - passwordVerify(password, hash).
     * @see HubClient::passwordVerify()
     *
     * @param string $password
     *
     * @return string
     * @throws HubIdApiException
     */
    public function passwordHash($password)
    {
        $pass = $this->request('post', '/password/hash', ['password' => $password])->getContent();
        if (!$pass['status'] || empty($pass['data'])) {
            throw new HubIdApiException('Failed to generate password');
        }

        return $pass['data'];
    }

    /**
     * Verifies a given password created.
     *
     * @param string $password User's plain text password
     * @param string $hash     The very password hash stored in the database.
     *
     * @see HubClient::passwordHash()
     *
     * @return array
     */
    public function passwordVerify($password, $hash)
    {
        return $this->request('post', '/password/verify', ['password' => $password, 'hash' => $hash])->getContent();
    }

    /**
     * Use this to get a long living refresh token.
     *
     * @param string $token a valid access token.
     *
     * @return HubClient
     */
    public function refreshToken($token)
    {
        $newToken = $this->setToken($token)->request('put', '/token')->getContent();
        if (!empty($newToken['data']['token'])) {
            return $this->setToken($newToken['data']['token']);
        }

        return $newToken;
    }

    /**
     * Authorize on the site, in response we receive a token and user ID.
     *
     * @param array $userCredentials [email: '', password: '']
     *
     * @return array|string
     */
    public function auth($userCredentials)
    {
        $v = new Validator($userCredentials);
        $v->rule('required', ['email', 'password'])->message('{field} - is required');
        if (!$v->validate()) {
            return $this->fail($v->errors());
        }

        $authorize = $this->request('post', '/auth', $userCredentials);
        try {
            $response = $authorize->getContent('data');

            if (!empty($response['token'])) {
                $this->setToken($response['token']);

                return $this->success($response);
            }

            if (isset($response['errors']['error'])) {
                return $this->fail($response['errors']['error']);
            }
            if (!isset($response['token']) && !empty($response['error'])) {
                return $this->fail($response['error']);
            }

            if (false == $response['status']) {
                return $this->fail($response['errors']);
            }
        } catch (\Exception $e) {
            return 'Auth Error!';
        }

        return 'Auth Error!';
    }

    public function logout()
    {
        self::$token = null;
        setcookie(self::COOKIE_TOKEN_NAME, null, null, '/');
        @unlink(__DIR__ . '/token');
    }

    /**
     * @param null $field
     *
     * @return mixed
     * @throws HubIdApiException
     */
    public function getContent($field = null)
    {
        $objectResponse = $this->response;
        if (isset($objectResponse['error']) && 'token_expired' === $objectResponse['error']) {
            $options = [];
            if (isset($this->request[2])) {
                $options = $this->request[2];
                $options = '[' . implode('","', $options) . ']';
                unset($this->request[2]);
            }

            return eval('return $this->refreshToken($this->getToken())->request("' . implode('","',
                    $this->request) . ', ' . $options . '")->getContent(' . $field . ');');
        }

        if (!is_null($field) && isset($objectResponse[$field])) {
            return $objectResponse[$field];
        }
        if (!empty($objectResponse['error'])) {
            throw new HubIdApiException($objectResponse['error']);
        }

        $objectResponse['status'] = 'success' === $objectResponse['status'];

        return $objectResponse;
    }

    public function request($method, $uri, $parameters = [])
    {
        $this->request = func_get_args();

        try {
            $this->config['token'] = $this->getToken();
            $this->response = parent::postFormData($uri, $parameters);
        } catch (HubIdApiException $ex) {
            $this->response = $ex->getMessage();
        }

        return $this;
    }

    private function getToken()
    {
        if (!empty(self::$token)) {
            return self::$token;
        }

        return !empty($_COOKIE[self::COOKIE_TOKEN_NAME]) ? $_COOKIE[self::COOKIE_TOKEN_NAME] : null;
    }

    /**
     * Apply the token to the request.
     *
     * @param string token - required
     *
     * @return HubClient
     */
    private function setToken($token)
    {
        self::$token = $token;
        setcookie(self::COOKIE_TOKEN_NAME, self::$token, time() + 3600 * 24 * 365, '/');
        $this->setAccessToken($token);

        return $this;
    }

    private function success($data)
    {
        return [
            'status' => true,
            'error' => null,
            'data' => $data,
        ];
    }

    private function fail($error)
    {
        return [
            'status' => false,
            'error' => $error,
            'data' => null,
        ];
    }
}
