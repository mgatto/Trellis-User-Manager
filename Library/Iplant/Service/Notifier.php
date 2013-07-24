<?php
/**
 *
 * Enter description here ...
 * @author mgatto
 *
 */

namespace Iplant\Service;

use Monolog\Logger,
    Monolog\Handler\StreamHandler;

/**
 *
 * Enter description here ...
 * @author mgatto
 *
 * @TODO add way to add a chain of notifiers! a la Symfony2 style.
 */
class Notifier
{
    /**
     * The type of notification: mail, rss, irc, jabber etc.
     *
     * @var string
     */
    private $type;

    /**
     * Monolog\Logger
     * @var
     */
    private $logger;

    /**
     * To whom the notification is sent
     *
     * @var array
     */
    private $recipients;

    /**
     *
     * Enter description here ...
     * @var unknown_type
     */
    private $subject;

    public function __construct($type, array $params) {
        $this->setType($type);

        /* handle arbituary parameters */
        foreach ( $params as $key => $value ) {
            $this->$key = $value;
        }

        $this->logger = new Logger('Emails');
        $this->logger->pushHandler(new StreamHandler(__DIR__ . '/../../../Logs/emails.log', Logger::INFO));
    }

    public function notify($html_body, $plain_text_body = null) {
        $body = array(
            'html' => $html_body,
            'text' => $plain_text_body,
        );

        /* Only mail is implemented at this time; other can be added as the
         * need arises */
        $type = $this->getType();
        switch ($type) {
            case 'mail':
                return $this->byMail($body, $this->getRecipients(), $this->subject);
                break;

            default:
                break;
        }
    }

    /**
     *
     * @return null
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set to private/protected to prevent programmers from changing the
     * type of notifier in controllers. This is possibly necessary since
     * types and recipients are closely bound together and should be forced to
     * be specified at the same time, i.e. in the constructor.
     *
     *
     * @param string $type
     *
     * @return
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     *
     * @return
     */
    public function getRecipients()
    {
        return $this->recipients;
    }

    /**
     *
     * @param $recipients
     */
    public function setRecipients(array $recipients)
    {
        $this->recipients = $recipients;
    }

    /**
     *
     * Enter description here ...
     *
     * @param array $body
     * @param array $recipients
     * @param string $subject
     *
     * @return return_type
     */
    protected function byMail(array $body, array $recipients, $subject = "[Test] New User Registration") {
        try {
            $mail = new \Zend_Mail('UTF-8');

            /* Part of SPAM fighting  */
            $transport = new \Zend_Mail_Transport_Sendmail('-fsupport@iplantcollaborative.org');
            $mail->setDefaultTransport($transport);

            $mail->setReplyTo('support@iplantcollaborative.org', 'iPlant Support');
            $mail->addHeader('X-Mailer:', 'PHP/'.phpversion());

            /* Zend_Mail can accept an array of recipients */
            $mail->addTo($recipients);

            $mail->setFrom("reg@iplantcollaborative.org", "iPlant User Manager");
            $mail->setSubject($subject);
            $mail->setBodyHtml($body['html']);
            if ( ! empty($body['text']) ) {
                $mail->setBodyText($body['text']);
            }

            $mail->send();

            /* we log all outgoing emails */
            $this->logger->addInfo("\r\nTo: " . join(',', $recipients) . "\r\nSubject: '{$subject}'");

        } catch (\Exception $e) {
            return false;
        }

        return true;
    }
}
