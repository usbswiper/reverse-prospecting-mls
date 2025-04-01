<?php

abstract class ApiBase {
    /**
     * @var wpdb $wpdb
     */
    protected $wpdb;
    protected function getHeader(?string $headerName = null)
    {
        $headers = getallheaders();
        return empty($headerName) ? $headers : (isset($headers[$headerName]) ? $headers[$headerName] : null);
    }

    private static function sendResponse(array $response)
    {
        header('Content-Type: application/json');
        die(json_encode($response));
    }

    public static function sendSuccessResponse($data)
    {
        self::sendResponse(['status' => true, 'data' => $data]);
    }

    public static function sendFailedResponse($data)
    {
        self::sendResponse(['status' => false, 'data' => $data]);
    }

    public static function cors() {
        // Allow from any origin
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            // Decide if the origin in $_SERVER['HTTP_ORIGIN'] is one
            // you want to allow, and if so:
            header("Accept: */*");
            header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');    // cache for 1 day
        }

        // Access-Control headers are received during OPTIONS requests
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
                // may also be using PUT, PATCH, HEAD etc
                header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
                header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

            exit(0);
        }
    }

    protected function connectDB()
    {
        $this->wpdb = new wpdb(DB_USER, DB_PASS, DB_NAME, DB_HOST);
    }
}