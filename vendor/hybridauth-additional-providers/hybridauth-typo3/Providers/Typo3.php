<?php
/*!
* HybridAuth
* http://hybridauth.sourceforge.net | https://github.com/hybridauth/hybridauth
*  (c) 2009-2015 HybridAuth authors | hybridauth.sourceforge.net/licenses.html
*/
use Facebook\PersistentData\PersistentDataFactory;
use Facebook\PersistentData\PersistentDataInterface;
use Facebook\PseudoRandomString\PseudoRandomStringGeneratorFactory;
use Facebook\PseudoRandomString\PseudoRandomStringGeneratorInterface;

/**
 * Hybrid_Providers_Typo3 provider adapter based on OAuth2 protocol
 *
 * The Provider is very similar to standard Oauth2 providers with a few differences:
 * - it sets the Content-Type header explicitly to application/x-www-form-urlencoded
 *   as required by Amazon
 * - it uses a custom OAuth2Client, because the built-in one does not use http_build_query()
 *   to set curl POST params, which causes cURL to set the Content-Type to multipart/form-data
 *
 * @property Typo3OAuth2Client $api
 */
class Hybrid_Providers_Typo3 extends Hybrid_Provider_Model_OAuth2
{

    /**
     * @const int The length of CSRF string to validate the login link.
     */
    const CSRF_LENGTH = 32;

    /**
     * Provider API wrapper
     * @var Typo3OAuth2Client
     */
    public $api = null;

    /**
     * @var PersistentDataInterface
     */
    protected $persistentDataHandler = null;

    /**
     * @var PseudoRandomStringGeneratorInterface
     */
    protected $pseudoRandomStringGenerator = null;

    /**
     * IDp wrappers initializer
     */
    public function initialize()
    {

        if (!$this->config['keys']['id'] || !$this->config['keys']['secret']) {
            throw new Exception("Your application id and secret are required in order to connect to {$this->providerId}.", 1497528188);
        }
        if (!$this->config['urls']['apibase'] || !$this->config['urls']['authorize'] || !$this->config['urls']['token']) {
            throw new Exception("Your application base api, authorize and token URLs (OAuth2 endpoints) are required in order to connect to {$this->providerId}.", 1497528189);
        }

        // override requested scope
        if (isset($this->config['scope']) && !empty($this->config['scope'])) {
            $this->scope = $this->config['scope'];
        }

        // include OAuth2 client
        require_once Hybrid_Auth::$config['path_libraries'] . 'OAuth/OAuth2Client.php';
        require_once Hybrid_Auth::$config['path_libraries'] . 'Typo3/Typo3OAuth2Client.php';

        // create a new OAuth2 client instance
        $this->api = new Typo3OAuth2Client(
            $this->config['keys']['id'],
            $this->config['keys']['secret'],
            $this->endpoint,
            $this->compressed
        );

        $this->api->api_base_url = $this->config['urls']['apibase'];
        $this->api->authorize_url = $this->config['urls']['authorize'];
        $this->api->token_url = $this->config['urls']['token'];
        $this->api->userprofile_url = $this->config['urls']['userprofile'];

        $this->api->curl_header = array('Content-Type: application/x-www-form-urlencoded');

        // If we have an access token, set it
        if ($this->token('access_token')) {
            $this->api->access_token = $this->token('access_token');
            $this->api->refresh_token = $this->token('refresh_token');
            $this->api->access_token_expires_in = $this->token('expires_in');
            $this->api->access_token_expires_at = $this->token('expires_at');
        }

        // Set curl proxy if exists
        if (isset(Hybrid_Auth::$config['proxy'])) {
            $this->api->curl_proxy = Hybrid_Auth::$config['proxy'];
        }

        $this->persistentDataHandler = PersistentDataFactory::createPersistentDataHandler('session');
        $this->pseudoRandomStringGenerator = PseudoRandomStringGeneratorFactory::createPseudoRandomStringGenerator('');
    }

    /**
     * {@inheritdoc}
     */
    public function loginBegin()
    {
        $state = $this->persistentDataHandler->get('state') ?: $this->pseudoRandomStringGenerator->getPseudoRandomString(static::CSRF_LENGTH);
        $this->persistentDataHandler->set('state', $state);

        $parameters = ['state' => $state];
        $optionals = ['redirect_uri', 'state'];

        foreach ($optionals as $parameter) {
            if (isset($this->config[$parameter]) && !empty($this->config[$parameter])) {
                $parameters[$parameter] = $this->config[$parameter];
            }
            if (isset($this->config['state']) && !empty($this->config['state'])) {
                $this->scope = $this->config['state'];
            }
        }

        Hybrid_Auth::redirect($this->api->authorizeUrl($parameters));
    }

    /**
     * load the user profile from the IDp api client
     */
    public function getUserProfile()
    {
        $params = [
            'fields' => implode(',', $this->trimExplode(',', $this->config['fields'], true)),
            'access_token' => $this->api->access_token
        ];
        $data = $this->api->get($this->api->userprofile_url, $params);
        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        if (!isset($data['identifier']) || !isset($data['uid'])) {
            throw new Exception("User profile request failed! {$this->providerId} returned an invalid response.", 1497533436);
        }

        foreach ($data as $key => $value) {
            if (property_exists($this->user->profile, $key)) {
                $this->user->profile->$key = $value;
            }
        }
        if (empty($this->user->profile->identifier) && isset($data['uid'])) {
            $this->user->profile->identifier = $data->uid;
        }
        if (!empty($this->user->profile->email)) {
            $this->user->profile->emailVerified = $this->user->profile->email;
        }

        return $this->user->profile;
    }

    /**
     * Explodes a string and trims all values for whitespace in the end.
     * If $onlyNonEmptyValues is set, then all blank ('') values are removed.
     *
     * @param string $delim Delimiter string to explode with
     * @param string $string The string to explode
     * @param bool $removeEmptyValues If set, all empty values will be removed in output
     * @param int $limit If limit is set and positive, the returned array will contain a maximum of limit elements with
     *                   the last element containing the rest of string. If the limit parameter is negative, all components
     *                   except the last -limit are returned.
     * @return array Exploded values
     */
    protected function trimExplode($delim, $string, $removeEmptyValues = false, $limit = 0)
    {
        $result = explode($delim, $string);
        if ($removeEmptyValues) {
            $temp = [];
            foreach ($result as $value) {
                if (trim($value) !== '') {
                    $temp[] = $value;
                }
            }
            $result = $temp;
        }
        if ($limit > 0 && count($result) > $limit) {
            $lastElements = array_splice($result, $limit - 1);
            $result[] = implode($delim, $lastElements);
        } elseif ($limit < 0) {
            $result = array_slice($result, 0, $limit);
        }
        $result = array_map('trim', $result);
        return $result;
    }
}
