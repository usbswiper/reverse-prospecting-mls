<?php

const DEBUG_MODE = true;
const DEBUG_MODE_EMAIL = ['deepakmaurya@hotmail.com' => 'Deepak Maurya'];
const SCHEDULED_EMAILS_TABLE = 'scheduled_emails';
const SCHEDULED_EMAILS_LIST_TABLE = 'emails_list';
const UNSUBSCRIBE_TABLE = 'unsubscribe_list';
if ($_SERVER['HTTP_HOST'] == 'localhost:8000') {
    define("DB_HOST", '127.0.0.1');
    define("DB_PORT", '3306');
    define("DB_USER", 'root');
    define("DB_PASS", '123456');
    define("DB_NAME", 'scrap_extension_db');
    define("BASE_URL", 'http://localhost:8000');
    define("IMAGE_BASE_URL", BASE_URL . '/images');
} else {
    define("DB_HOST", '127.0.0.1');
    define("DB_PORT", '3306');
    define("DB_USER", '');
    define("DB_PASS", '');
    define("DB_NAME", '');
    define("BASE_URL", 'https://reverse-prospecting.com/mls_api');
    define("IMAGE_BASE_URL", BASE_URL . '/images');
}

const EMAIL_UNSUBSCRIBE_SECRET = '';
const AUTH_TOKEN = '';
const MAIL_FROM = 'rjfreedkinrealtor@reverse-prospecting.com';
const MAIL_FROM_NAME = 'Reverse-prospecting.com';
const WP_DEBUG = false;
const WP_DEBUG_DISPLAY = false;

const SMTP_HOST = '';
const SMTP_USERNAME = '';
const SMTP_PASSWORD = '';
const SMTP_PORT = 465;
const ADMIN_REPORT_EMAILS = ['rjfreedkinrealtor@reverse-prospecting.com' => 'Reverse Prospecting'];

const AWS_REGION = 'us-east-1';
const AWS_ACCESS_KEY_ID = '';
const AWS_ACCESS_KEY_SECRET = '';

const MAIL_MODE = 'aws'; // aws or smtp
