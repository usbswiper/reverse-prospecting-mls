<?php
require_once (dirname(__FILE__) . '/bootstrapper.php');

class Unsubscribe extends ApiBase
{
    public function unsubscribeUser($email, $reason = 'UNSUBSCRIBED')
    {
        $this->connectDB();
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $email = $this->extractEmails($email);
        }
        return $this->unsubscribeEmail($this->wpdb, $email, $reason);
    }

    private function extractEmails($string){
        preg_match_all("/[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+/i", $string, $matches);
        return isset($matches[0][0]) ? $matches[0][0] : null;
    }

    public static function isEmailUnsubscribed(wpdb $wpdb, $email)
    {
        $isExist = $wpdb->get_row($wpdb->prepare("select * from " . UNSUBSCRIBE_TABLE . " where email = %s", $email), ARRAY_A);
        if ($isExist) {
            return $isExist;
        }
        return false;
    }

    public static function unsubscribeEmail(wpdb $wpdb, $email, $reason)
    {
        if (empty($email)) {
            throw new Exception('Empty email, please pass an email address to unsubscribe.');
        }
        $inserted = $wpdb->insert(UNSUBSCRIBE_TABLE, [
            'email' => $email,
            'reason' => $reason
        ]);
        if (!$inserted && str_contains($wpdb->last_error, 'Duplicate entry')) {
            throw new Exception('This email is already unsubscribed.');
        }
        if (!$inserted) {
            throw new Exception('An error occurred with unsubscribe, please contact support.' . $wpdb->last_error);
        }
        return 'Email has been unsubscribed successfully.';
    }

    private function getSecretToken($email)
    {
        return md5($email . '_' . EMAIL_UNSUBSCRIBE_SECRET);
    }

    public function getUnsubscribeLink($email)
    {

        return BASE_URL . '/unsubscribe.php?email=' . urlencode($email) . '&token=' . $this->getSecretToken($email);
    }

    /**
     * @throws Exception
     */
    public function verifyUnsubscribeRequest($email, $verifyToken)
    {
        if ($verifyToken !== $this->getSecretToken($email)) {
            throw new Exception('Invalid unsubscribe url. please click valid link.');
        }
        return true;
    }

    public static function logErrors($string)
    {
        error_log($string . "\n", 3, dirname(__FILE__) . '/logs/scrapper.log');
    }

    public function handleBounceOrComplaint()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        //if ($data['Type'] == 'SubscriptionConfirmation') {
            self::logErrors("Dump: " . json_encode($data));
        //}
        $successMessage = 'success';
        if ($data['Type'] == 'Notification') {
            $message = json_decode($data['Message'], true);
            //var_dump($message);die;
            switch ($message['notificationType']) {
                case 'Bounce':
                    $bounce = $message['bounce'];
                    foreach ($bounce['bouncedRecipients'] as $bouncedRecipient) {
                        $emailAddress = $bouncedRecipient['emailAddress'];
                        try {
                            $this->unsubscribeUser($emailAddress, 'BOUNCE_REPORT');
                        } catch (Exception $exception) {
                            $successMessage = $exception->getMessage();
                        }
                    }
                    break;
                case 'Complaint':
                    $complaint = $message['complaint'];
                    foreach ($complaint['complainedRecipients'] as $complainedRecipient) {
                        $emailAddress = $complainedRecipient['emailAddress'];
                        try {
                            $this->unsubscribeUser($emailAddress, 'COMPLAINT_REPORT');
                        } catch (Exception $exception) {
                            $successMessage = $exception->getMessage();
                        }
                    }
                    break;
                default:
                    // Do Nothing
                    break;
            }
        }
        header('Content-Type: application/json');
        return die(json_encode(['status' => 200, "message" => $successMessage]));
    }
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Unsubscribe action from user
if (isset($_REQUEST['email'], $_REQUEST['token']) && !empty($_REQUEST['email']) && !empty($_REQUEST['token'])) {
    $unsub = new Unsubscribe();
    try {
        $decodedEmail = rawurldecode($_REQUEST['email']);
        $unsub->verifyUnsubscribeRequest($decodedEmail, $_REQUEST['token']);
        $message = $unsub->unsubscribeUser($decodedEmail);
        die('<h1 style="text-align: center;color: green;margin: 20%">' . $message . '</h1>');
    } catch (Exception $e) {
        die('<h1 style="text-align: center;color: red;margin: 20%">' . $e->getMessage() . '</h1>');
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $unsub = new Unsubscribe();
    try {
        $unsub->handleBounceOrComplaint();
    } catch (Exception $e) {
        $unsub::logErrors('An error occurred in handling post request: ' . $e->getMessage());
    }
}