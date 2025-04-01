<?php

use Aws\Credentials\Credentials;

require_once (dirname(__FILE__).'/bootstrapper.php');
require_once (dirname(__FILE__).'/unsubscribe.php');

class Cron_Job_Send_Emails extends ApiBase {
    private $apiKey = 'jhas78dsa7d7asghf765sadghjsa872hgj';
    private \PHPMailer\PHPMailer\PHPMailer $mail;
    private \Aws\Ses\SesClient $sesClient;

    public function __construct()
    {
        if (MAIL_MODE == 'aws') {
            $this->awsSesSetup();
        } else {
            $this->smtpSetup();
        }
    }

    private function smtpSetup()
    {
        $mail = new PHPMailer\PHPMailer\PHPMailer();
        $mail->From = MAIL_FROM;
        $mail->FromName = MAIL_FROM_NAME;
        $mail->SMTPDebug  = 0;
        /*$mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = "ssl";
        $mail->Port = SMTP_PORT;*/

        $this->mail = $mail;
    }

    private function awsSesSetup()
    {
        $options = [
            'region' => AWS_REGION,
            'version' => 'latest',
        ];

        $credentials = new Credentials(AWS_ACCESS_KEY_ID, AWS_ACCESS_KEY_SECRET);
        $options['credentials'] = $credentials;
        $this->sesClient = new \Aws\Ses\SesClient($options);
    }

    public function sendEmails()
    {
        if (empty($_REQUEST['api_key']) || $_REQUEST['api_key'] != $this->apiKey) {
            die('Invalid request');
        }
        $this->connectDB();
        if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'summary') {
            $this->sendReportSummary();
            die('Summary Cron job completed successfully.');
        } else {
            $this->sendChunkEmails();
            die('Cron job completed successfully.');
        }
    }

    /**
     * Set a cron to run this function every hour
     * @throws Exception
     */
    private function sendReportSummary()
    {
        $limit = 10;
        if (DEBUG_MODE) $limit = 3;
        $allResults = $this->wpdb->get_results("select * from ".SCHEDULED_EMAILS_TABLE." where report_sent = 0 limit $limit", ARRAY_A);
        foreach ($allResults as $allResult) {
            $allSubResults = intval($this->wpdb->get_var("select count(*) from ".SCHEDULED_EMAILS_LIST_TABLE." where is_processed = 0 and batch_id = ".$allResult['id']));
            if ($allSubResults == 0) {
                $firstScheduledEmailForAddress = $this->wpdb->get_row("select * from " . SCHEDULED_EMAILS_LIST_TABLE . " where batch_id = " . $allResult['id'], ARRAY_A);
                $emailData = json_decode($firstScheduledEmailForAddress['email_data'], true);
                $allSuccessful = intval($this->wpdb->get_var("select count(*) from ".SCHEDULED_EMAILS_LIST_TABLE." where is_processed = 1 and batch_id = ".$allResult['id']));
                $allFailed = $this->wpdb->get_results("select error_details, count(*) as total_fails from ".SCHEDULED_EMAILS_LIST_TABLE." where is_processed = 2 and batch_id = ".$allResult['id'] . " group by error_details", ARRAY_A);

                $failedSummary = '';
                $totalFailedEmails = 0;
                foreach ($allFailed as $failed) {
                    switch ($failed['error_details']) {
                        case 'UNSUBSCRIBED':
                            $failedSummary .= "<b>Failed (Unsubscribed):</b> " . $failed['total_fails'] . "<br>";
                            break;
                        case 'BOUNCE_REPORT':
                            $failedSummary .= "<b>Failed (Bounce Report):</b> " . $failed['total_fails'] . "<br>";
                            break;
                        case 'COMPLAINT_REPORT':
                            $failedSummary .= "<b>Failed (Complaint Report):</b> " . $failed['total_fails'] . "<br>";
                            break;
                        case 'INVALID_EMAILS':
                            $failedSummary .= "<b>Failed (Invalid emails):</b> " . $failed['total_fails'] . "<br>";
                            break;
                        default:
                            $failedSummary .= "<b>Failed (Unknown):</b> " . $failed['total_fails'] . "<br>";
                    }
                    $totalFailedEmails += $failed['total_fails'];
                }

                if ($totalFailedEmails > 0) {
                    $failedSummary = '<br><b>Total Failed Emails:</b> ' . $totalFailedEmails . '<br>' . $failedSummary;
                } else {
                    $failedSummary = null;
                }

                $htmlEmail = sprintf('<html><body>
Hi Admin,<br>
<br>
Last scheduled email batch #%s has been processed successfully.<br><br>
<b>Email Subject:</b> %s<br>
<b>Address:</b> %s<br>
<b>MLS #:</b> %s<br>
---------------------------<br>
<b>Total Scheduled Emails:</b> %s<br>
---------------------------<br>
<b>Sent Emails:</b> %s<br>%s
<br><br>
Regards,<br>
Webmaster Admin<br>
</body></html>', $allResult['id'], $emailData['emailSubject'], $emailData['address'], $emailData['mls'], $allResult['total_emails'], $allSuccessful, $failedSummary);
                
                $this->wpdb->update(SCHEDULED_EMAILS_TABLE, ['sent_emails' => $allSuccessful, 'failed_emails' => $totalFailedEmails,
                    'report_sent' => 1], ['id' => $allResult['id']]);
                $this->sendEmail(ADMIN_REPORT_EMAILS, 'Email Report Summary: #' . $allResult['id'], $htmlEmail);
                echo 'Batch #'. $allResult['id'].' summary sent'."\n";
            }
        }
    }

    /**
     * Set a cron to run this function every 15 min, if 1 email takes 6 sec then in 1 min = 10 emails
     * 13 mins = 130
     * wget -O /dev/null 'https://aetesting.xyz/mls_api/cron_job_send_emails.php?api_key=jhas78dsa7d7asghf765sadghjsa872hgj&action=summary' >/dev/null 2>&1
     * @throws Exception
     */
    private function sendChunkEmails()
    {
        $unsub = new Unsubscribe();
        set_time_limit(780);
        $limit = 120;
        if (DEBUG_MODE) $limit = 1;
        /**
         * Send the unprocessed emails in list or try failed emails once more
         */
        $emailSent = null;
        $allResults = $this->wpdb->get_results("select a.*, b.image_file, b.bedrooms, b.bathrooms, b.sqft, b.listed_at, b.city, b.subject_prefix, b.email_notes_1   from "
            . SCHEDULED_EMAILS_LIST_TABLE . " a inner join " . SCHEDULED_EMAILS_TABLE . " b"
            . " on a.batch_id = b.id"
            ." where a.is_processed in (0, 2) and a.total_send_retries <= 1 limit $limit", ARRAY_A);
        foreach ($allResults as $result) {
            $emailData = json_decode($result['email_data'], true);
            $emailData['custom_subject_prefix'] = $result['subject_prefix'] ?? '';
            $emailData['custom_email_notes_1'] = $result['email_notes_1'] ?? '';
            $emailData['custom_city'] = $result["city"];
            $emailData['custom_bedrooms'] = $result["bedrooms"];
            $emailData['custom_bathrooms'] = $result["bathrooms"];
            $emailData['custom_sqft'] = $result["sqft"];
            $emailData['custom_listed_at'] = $result["listed_at"];
            $emailData['custom_image_file'] = $result["image_file"];
            $errorMessage = null;
            $addresses = explode(';', $emailData['email']);
            try {
                $noEmailReason = 'INVALID_EMAILS';
                $addrWithRecpName = [];
                foreach ($addresses as $address) {
                    $isEmailUnsubscribed = Unsubscribe::isEmailUnsubscribed($this->wpdb, $address);
                    if ($isEmailUnsubscribed === false) {
                        $addrWithRecpName[$address] = $emailData['agentName'];
                    } else {
                        $noEmailReason = $isEmailUnsubscribed['reason'];
                    }
                }
                if (count($addrWithRecpName)) {
                    foreach ($addrWithRecpName as $addr => $name) {
                        $emailData['unsubscribeLink'] = $unsub->getUnsubscribeLink($addr);
                        $this->sendEmail([$addr => $name], $emailData['custom_subject_prefix'].$emailData['emailSubject'], getEmailBody($emailData));
                        $emailSent = true;
                    }
                } else {
                    throw new Exception($noEmailReason);
                }
            } catch (Exception $exception) {
                $emailSent = $emailSent == null ? false : $emailSent;
                $errorMessage = $exception->getMessage();
            }
            $this->wpdb->update(SCHEDULED_EMAILS_LIST_TABLE, [
                'is_processed' => ($emailSent ? 1 : 2),
                'error_details' => $errorMessage,
                'total_send_retries' => $result['total_send_retries'] + 1
            ], ['id' => $result['id']]);
            // send next email after 4 seconds
            sleep(4);
        }
    }

    /**
     * @throws Exception
     */
    private function sendEmail(array $addresses, $subject, $message)
    {
        if (DEBUG_MODE) {
            $addresses = DEBUG_MODE_EMAIL;
        }

        if (MAIL_MODE == 'aws') {
            return $this->awsEmail($addresses, $subject, $message);
        } else {
            return $this->smtpEmail($addresses, $subject, $message);
        }
    }

    private function awsEmail(array $addresses, $subject, $message)
    {
        try {
            $charSet = 'UTF-8';
            $recipients = array_keys($addresses);
            $result = $this->sesClient->sendEmail([
                'Destination' => [
                    'ToAddresses' => $recipients,
                ],
                'ReplyToAddresses' => [MAIL_FROM],
                'Source' => MAIL_FROM,
                'Message' => [
                    'Body' => [
                        'Html' => [
                            'Charset' => $charSet,
                            'Data' => $message,
                        ],
                        'Text' => [
                            'Charset' => $charSet,
                            'Data' => strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message)),
                        ],
                    ],
                    'Subject' => [
                        'Charset' => $charSet,
                        'Data' => $subject,
                    ],
                ]
            ]);
            return $result['MessageId'];
        } catch (\Aws\Exception\AwsException $e) {
            throw new Exception("AWS Error: " . $e->getAwsErrorMessage());
        }
    }

    private function smtpEmail(array $addresses, $subject, $message)
    {
        $mail = $this->mail;
        $mail->clearAllRecipients();
        foreach ($addresses as $address => $recpName) {
            $mail->addAddress($address, $recpName);
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);
        if (!$mail->send()) {
            throw new Exception("Mailer Error: " . $mail->ErrorInfo);
        }
        return true;
    }

}
date_default_timezone_set('America/Chicago');
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
(new Cron_Job_Send_Emails())->sendEmails();