<?php

namespace Iplant\Service;

use Symfony\Component\HttpFoundation\Request;

/**
 * Class for abstrating calling Recaptcha out of the DataTransformer
 * so it can be test with a mock of this class.
 *
 * @author Henrik Bjornskov <henrik@bjrnskov.dk>
 */
class Recaptcha
{
    /**
     * @var unknown_type
     */
    protected $verify_server_url = "https://www.google.com/recaptcha/api/verify";

    /**
     * @var Request $request
     */
    protected $request;

    /**
     * @var string $private_key
     */
    protected $private_key;

    /**
     * @param Request $request
     * @param string $private_key
     */
    public function __construct(Request $request, $private_key)
    {
        $this->request = $request;
        $this->private_key = $private_key;
    }

    /**
     * Uses a stream context to call the api and validating the recaptcha
     * parameters
     *
     * @param string $challenge
     * @param string $response
     */
    public function isValid($challenge = null, $response = null)
    {
        $parameters = array(
            'remoteip'    => $this->request->server->get('REMOTE_ADDR', '127.0.0.1'),
            'privatekey' => $this->private_key,
            'response'    => $this->request->get('recaptcha_response_field', $response),
            'challenge'   => $this->request->get('recaptcha_challenge_field', $challenge),
        );

        $query_string = utf8_encode(http_build_query($parameters));
        $context = stream_context_create(array(
            'http' => array(
                'method'  => 'POST',
                'content' => $query_string,
                'header'  => array(
                    'Content-type: application/x-www-form-urlencoded',
                    'Content-Length: ' . strlen($query_string),
                    'User-Agent: reCAPTCHA/PHP',
                ),
            ),
        ));

        $result = file_get_contents($this->verify_server_url, false, $context);

        if (false !== strpos($result, 'true', 0)) {
            return true;
        }

        return false;
    }
}
