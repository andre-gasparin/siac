<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Spreadsheet Email Reader Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for automated email checking (Cron 1) to capture spreadsheet
    | attachments and map them to configured spreadsheet import templates.
    |
    */

    'enabled' => env('SPREADSHEET_EMAIL_ENABLED', true),

    'protocol' => env('SPREADSHEET_EMAIL_PROTOCOL', 'imap'), // 'imap' or 'pop3'

    'host' => env('SPREADSHEET_EMAIL_HOST', 'imap.example.com'),

    'port' => (int) env('SPREADSHEET_EMAIL_PORT', 993),

    'encryption' => env('SPREADSHEET_EMAIL_ENCRYPTION', 'ssl'), // 'ssl', 'tls', or null

    'validate_cert' => env('SPREADSHEET_EMAIL_VALIDATE_CERT', true),

    'username' => env('SPREADSHEET_EMAIL_USERNAME', ''),

    'password' => env('SPREADSHEET_EMAIL_PASSWORD', ''),

    'folder' => env('SPREADSHEET_EMAIL_FOLDER', 'INBOX'),

    'timeout' => (int) env('SPREADSHEET_EMAIL_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Storage Directory
    |--------------------------------------------------------------------------
    |
    | Directory on the local/default storage disk where downloaded email
    | spreadsheet attachments will be securely placed pending confirmation.
    |
    */
    'storage_disk' => env('SPREADSHEET_EMAIL_DISK', 'local'),
    'storage_path' => env('SPREADSHEET_EMAIL_PATH', 'spreadsheet_inbox'),
];
