<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);
require_once (dirname(__FILE__).'/bootstrapper.php');

class ScheduleEmails extends ApiBase {
    private $requestData;

    private function validateHeaders()
    {
        $auth = $this->getHeader('Authorization');
        if (empty($auth) || $auth !== AUTH_TOKEN) {
            throw new Exception('Authorization failed.');
        }
    }

    private function parseRequest()
    {
        $requestData = $_POST['formBody'] ?? [];
        if (!empty($requestData) && is_string($requestData)) {
            $requestData = json_decode($requestData, true);
        }
        if (!$requestData) {
            $requestData = [];
        }
        $this->requestData = $requestData;

        //$this->requestData = json_decode(file_get_contents('php://input'), true);
    }

    private function validateRequest()
    {
        $fieldsToValidate = ['emails', 'city', 'bedrooms', 'bathrooms', 'sqft', 'listedAt'];

        if (empty($this->requestData)) {
            throw new Exception('Data validation failed.');
        }
        foreach ($fieldsToValidate as $item) {
            if (!isset($this->requestData[$item]) || empty($this->requestData[$item])) {
                throw new Exception($item . ' validation failed.');
            }
        }
        if (!is_array($this->requestData['emails'])) {
            throw new Exception('emails validation failed.');
        }

        if (empty($_FILES['image']['name'])) {
            throw new Exception('please upload the image file.');
        }
        $imageFile = $_FILES['image']['name'];

        $ext = strtolower(pathinfo($imageFile, PATHINFO_EXTENSION));

        if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
            throw new Exception('please upload the correct image file (jpg, jpeg or png files accepted).');
        }
    }

    /**
     * @throws Exception
     */
    public function processRequest()
    {
        $this->validateHeaders();
        $this->parseRequest();
        $this->validateRequest();
        $this->connectDB();
        $isPreview = $this->requestData['isPreview'] ?? false;
        if ($isPreview) {
            return $this->preview();
        } else {
            return $this->schedule();
        }
    }

    private function preview()
    {
        // save image file to local - temporary
        $ext = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
        $targetFile = 'temporary/image_' . time() . '.' . $ext;
        if (!move_uploaded_file($_FILES["image"]["tmp_name"], 'uploaded/' . $targetFile)) {
            throw new Exception( "Sorry, there was an error uploading your file.");
        }

        $emailList = $this->requestData['emails'];
        $firstEmailRow = $emailList[0];
        $emailData = $firstEmailRow;
        $emailData['custom_subject_prefix'] = $this->requestData['subject_prefix'] ?? '';
        $emailData['custom_email_notes_1'] = $this->requestData['email_notes_1'] ?? '';
        $emailData['custom_city'] = $this->requestData["city"];
        $emailData['custom_bedrooms'] = $this->requestData["bedrooms"];
        $emailData['custom_bathrooms'] = $this->requestData["bathrooms"];
        $emailData['custom_sqft'] = $this->requestData["sqft"];
        $emailData['custom_listed_at'] = $this->requestData["listedAt"];
        $emailData['custom_image_file'] = $targetFile;
        $emailData['unsubscribeLink'] = 'https://example.com';
        $htmlBody = getEmailBody($emailData);
        $htmlBody = 'Subject: ' . $emailData['custom_subject_prefix'] . $emailData['emailSubject'] . '<br><br>' . $htmlBody;
        return ['message' => 'Email preview prepared successfully.', 'html' => $htmlBody];
    }

    private function schedule()
    {
        // save image file to local
        $ext = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
        $targetFile = 'image_' . time() . '.' . $ext;
        if (!move_uploaded_file($_FILES["image"]["tmp_name"], 'uploaded/'.$targetFile)) {
            throw new Exception( "Sorry, there was an error uploading your file.");
        }

        $emailList = $this->requestData['emails'];
        $scheduledBatch = $this->wpdb->insert(SCHEDULED_EMAILS_TABLE, [
            'created_at' => date('Y-m-d H:i:s'),
            'total_emails' => 0,
            'image_file' => $targetFile,
            'bedrooms' => $this->requestData['bedrooms'],
            'bathrooms' => $this->requestData['bathrooms'],
            'sqft' => $this->requestData['sqft'],
            'listed_at' => $this->requestData['listedAt'],
            'city' => $this->requestData['city'],
            'subject_prefix' => $this->requestData['subject_prefix'] ?? null,
            'email_notes_1' => $this->requestData['email_notes_1'] ?? null
        ]);
        if (!$scheduledBatch) {
            throw new Exception('An error occurred in scheduling the emails. ' . $this->wpdb->last_error);
        }
        $batchId = $this->wpdb->insert_id;
        $totalEmails = 0;
        foreach ($emailList as $singleEmail) {
            if (!empty($singleEmail['agentId']) && !empty($singleEmail['mls'])
                && !empty($singleEmail['address']) && !empty($singleEmail['agentName'])
                && !empty($singleEmail['email']) && !empty($singleEmail['emailSubject'])
            )
            $this->wpdb->insert(SCHEDULED_EMAILS_LIST_TABLE, [
                'batch_id' => $batchId,
                'agent_id' => $singleEmail['agentId'],
                'email_data' => json_encode($singleEmail)
            ]);
            $totalEmails++;
        }

        // Schedule last email to be sent to richard
        if (isset($singleEmail)) {
            $singleEmail['agentName'] = 'Richard';
            $singleEmail['email'] = 'richard@usbswiper.com';
            $singleEmail['emailSubject'] = 'Last queue email: ' . $singleEmail['emailSubject'];
            $this->wpdb->insert(SCHEDULED_EMAILS_LIST_TABLE, [
                'batch_id' => $batchId,
                'agent_id' => date('Ymdhis'),
                'email_data' => json_encode($singleEmail)
            ]);
            $totalEmails++;
        }

        $this->wpdb->update(SCHEDULED_EMAILS_TABLE, [
            'total_emails' => $totalEmails
        ], ['id' => $batchId]);

        return ['message' => $totalEmails . ' email(s) were scheduled successfully.'];
    }
}
ScheduleEmails::cors();
try {
    $response = (new ScheduleEmails())->processRequest();
    ScheduleEmails::sendSuccessResponse($response);
} catch (Exception $exception) {
    ScheduleEmails::sendFailedResponse(['message' => $exception->getMessage()]);
}