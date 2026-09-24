<?php

namespace App\Features\SpreadsheetImports\Services;

use DateTimeImmutable;
use Exception;
use Illuminate\Support\Facades\Log;

class IncomingEmailAttachment
{
    public function __construct(
        public string $filename,
        public string $content,
        public string $mimeType,
        public int $sizeBytes,
    ) {}
}

class IncomingEmailMessage
{
    /**
     * @param  IncomingEmailAttachment[]  $attachments
     */
    public function __construct(
        public string $messageId,
        public string $senderEmail,
        public ?string $senderName,
        public string $subject,
        public ?string $bodyText,
        public ?DateTimeImmutable $date,
        public array $attachments = [],
    ) {}
}

class SpreadsheetEmailReaderService
{
    /**
     * Fetch unread emails from the configured mailbox.
     *
     * @return IncomingEmailMessage[]
     */
    public function fetchUnreadEmails(): array
    {
        $enabled = (bool) config('spreadsheet_email.enabled', true);
        if (! $enabled) {
            Log::info('[SpreadsheetEmailReader] E-mail check disabled in configuration.');

            return [];
        }

        $host = (string) config('spreadsheet_email.host');
        $port = (int) config('spreadsheet_email.port', 993);
        $encryption = (string) config('spreadsheet_email.encryption', 'ssl');
        $username = (string) config('spreadsheet_email.username');
        $password = (string) config('spreadsheet_email.password');
        $folder = (string) config('spreadsheet_email.folder', 'INBOX');
        $protocol = (string) config('spreadsheet_email.protocol', 'imap');
        $validateCert = (bool) config('spreadsheet_email.validate_cert', true);

        if (empty($host) || empty($username) || empty($password)) {
            Log::warning('[SpreadsheetEmailReader] IMAP/POP3 host, username, or password is empty. Skipping email check.');

            return [];
        }

        if (extension_loaded('imap')) {
            return $this->fetchViaImapExtension($host, $port, $encryption, $validateCert, $folder, $protocol, $username, $password);
        }

        return $this->fetchViaSocket($host, $port, $encryption, $username, $password, $folder);
    }

    /**
     * @return IncomingEmailMessage[]
     */
    protected function fetchViaImapExtension(
        string $host,
        int $port,
        string $encryption,
        bool $validateCert,
        string $folder,
        string $protocol,
        string $username,
        string $password,
    ): array {
        $flags = '';
        if ($protocol === 'pop3') {
            $flags .= '/pop3';
        }
        if ($encryption === 'ssl') {
            $flags .= '/ssl';
        } elseif ($encryption === 'tls') {
            $flags .= '/tls';
        } else {
            $flags .= '/notls';
        }

        if (! $validateCert) {
            $flags .= '/novalidate-cert';
        }

        $mailbox = "{{$host}:{$port}{$flags}}{$folder}";

        $connection = @imap_open($mailbox, $username, $password, 0, 1);
        if (! $connection) {
            $lastError = imap_last_error();
            Log::error("[SpreadsheetEmailReader] Failed to connect to IMAP: {$lastError}");
            throw new Exception("Falha ao conectar no servidor de e-mail IMAP: {$lastError}");
        }

        try {
            $emails = imap_search($connection, 'UNSEEN');
            if (! $emails) {
                return [];
            }

            $messages = [];

            foreach ($emails as $msgNumber) {
                $header = imap_headerinfo($connection, $msgNumber);
                $structure = imap_fetchstructure($connection, $msgNumber);

                $messageId = $header->message_id ?? (string) $msgNumber;
                $subject = isset($header->subject) ? $this->decodeMimeHeader($header->subject) : '(Sem assunto)';
                $from = $header->from[0] ?? null;
                $senderEmail = $from ? ($from->mailbox.'@'.$from->host) : 'desconhecido@email.com';
                $senderName = isset($from->personal) ? $this->decodeMimeHeader($from->personal) : null;
                $date = isset($header->date) ? new DateTimeImmutable($header->date) : new DateTimeImmutable;

                $body = $this->getImapBody($connection, $msgNumber, $structure);
                $attachments = $this->getImapAttachments($connection, $msgNumber, $structure);

                $messages[] = new IncomingEmailMessage(
                    messageId: $messageId,
                    senderEmail: $senderEmail,
                    senderName: $senderName,
                    subject: $subject,
                    bodyText: $body,
                    date: $date,
                    attachments: $attachments,
                );

                // Mark as seen
                imap_setflag_full($connection, (string) $msgNumber, '\\Seen');
            }

            return $messages;
        } finally {
            @imap_close($connection);
        }
    }

    /**
     * Decode MIME encoded headers (e.g. =?UTF-8?B?...?=).
     */
    protected function decodeMimeHeader(string $text): string
    {
        $elements = imap_mime_header_decode($text);
        $decoded = '';
        foreach ($elements as $element) {
            $charset = strtolower($element->charset ?? 'default');
            $textChunk = $element->text;
            if ($charset !== 'default' && $charset !== 'utf-8') {
                $converted = @mb_convert_encoding($textChunk, 'UTF-8', $charset);
                $decoded .= $converted !== false ? $converted : $textChunk;
            } else {
                $decoded .= $textChunk;
            }
        }

        return $decoded;
    }

    /**
     * @return IncomingEmailAttachment[]
     */
    protected function getImapAttachments($connection, int $msgNumber, $structure, string $partNumber = ''): array
    {
        $attachments = [];

        if (isset($structure->parts) && count($structure->parts)) {
            foreach ($structure->parts as $index => $subStructure) {
                $currentPartNumber = $partNumber === '' ? (string) ($index + 1) : "{$partNumber}.".($index + 1);
                $subAttachments = $this->getImapAttachments($connection, $msgNumber, $subStructure, $currentPartNumber);
                $attachments = array_merge($attachments, $subAttachments);
            }
        } else {
            $filename = null;
            if ($structure->ifdparameters) {
                foreach ($structure->dparameters as $param) {
                    if (strtolower($param->attribute) === 'filename') {
                        $filename = $this->decodeMimeHeader($param->value);
                    }
                }
            }

            if (! $filename && $structure->ifparameters) {
                foreach ($structure->parameters as $param) {
                    if (strtolower($param->attribute) === 'name') {
                        $filename = $this->decodeMimeHeader($param->value);
                    }
                }
            }

            if ($filename) {
                $partNum = $partNumber === '' ? '1' : $partNumber;
                $rawContent = imap_fetchbody($connection, $msgNumber, $partNum);

                if ($structure->encoding === 3) { // Base64
                    $content = base64_decode($rawContent);
                } elseif ($structure->encoding === 4) { // Quoted-Printable
                    $content = quoted_printable_decode($rawContent);
                } else {
                    $content = $rawContent;
                }

                $mimeType = $this->getMimeTypeFromStructure($structure);

                $attachments[] = new IncomingEmailAttachment(
                    filename: $filename,
                    content: $content,
                    mimeType: $mimeType,
                    sizeBytes: strlen($content),
                );
            }
        }

        return $attachments;
    }

    protected function getMimeTypeFromStructure($structure): string
    {
        $primaryTypes = ['TEXT', 'MULTIPART', 'MESSAGE', 'APPLICATION', 'AUDIO', 'IMAGE', 'VIDEO', 'OTHER'];
        $type = $primaryTypes[$structure->type ?? 3] ?? 'APPLICATION';
        $subtype = $structure->subtype ?? 'OCTET-STREAM';

        return strtolower("{$type}/{$subtype}");
    }

    protected function getImapBody($connection, int $msgNumber, $structure): string
    {
        $body = '';
        if ($structure->type === 0) { // Text
            $body = imap_fetchbody($connection, $msgNumber, '1');
            if ($structure->encoding === 3) {
                $body = base64_decode($body);
            } elseif ($structure->encoding === 4) {
                $body = quoted_printable_decode($body);
            }
        } elseif (isset($structure->parts) && count($structure->parts)) {
            // Find text/plain part or text/html
            foreach ($structure->parts as $index => $part) {
                if ($part->type === 0 && strtoupper($part->subtype ?? '') === 'PLAIN') {
                    $partBody = imap_fetchbody($connection, $msgNumber, (string) ($index + 1));
                    if ($part->encoding === 3) {
                        $partBody = base64_decode($partBody);
                    } elseif ($part->encoding === 4) {
                        $partBody = quoted_printable_decode($partBody);
                    }
                    $body .= $partBody;
                    break;
                }
            }
        }

        return strip_tags($body);
    }

    /**
     * Fallback stream socket implementation for pure PHP IMAP when ext-imap is not loaded.
     *
     * @return IncomingEmailMessage[]
     */
    protected function fetchViaSocket(
        string $host,
        int $port,
        string $encryption,
        string $username,
        string $password,
        string $folder,
    ): array {
        $prefix = $encryption === 'ssl' ? 'ssl://' : ($encryption === 'tls' ? 'tls://' : 'tcp://');
        $timeout = (int) config('spreadsheet_email.timeout', 30);

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => (bool) config('spreadsheet_email.validate_cert', true),
                'verify_peer_name' => (bool) config('spreadsheet_email.validate_cert', true),
            ],
        ]);

        $fp = @stream_socket_client(
            "{$prefix}{$host}:{$port}",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (! $fp) {
            Log::error("[SpreadsheetEmailReader] Socket connection failed: {$errstr} ({$errno})");
            throw new Exception("Falha na conexão de socket com o servidor de e-mail: {$errstr}");
        }

        try {
            stream_set_timeout($fp, $timeout);
            // Read greeting
            fgets($fp);

            // Login
            fwrite($fp, "A01 LOGIN \"{$username}\" \"{$password}\"\r\n");
            $response = $this->readSocketUntilTag($fp, 'A01');
            if (! str_contains($response, 'A01 OK')) {
                throw new Exception("Falha na autenticação IMAP: {$response}");
            }

            // Select Folder
            fwrite($fp, "A02 SELECT \"{$folder}\"\r\n");
            $this->readSocketUntilTag($fp, 'A02');

            // Search Unseen
            fwrite($fp, "A03 SEARCH UNSEEN\r\n");
            $searchResp = $this->readSocketUntilTag($fp, 'A03');

            preg_match('/\* SEARCH (.*)/i', $searchResp, $matches);
            $msgIds = isset($matches[1]) ? array_filter(explode(' ', trim($matches[1]))) : [];

            $messages = [];
            foreach ($msgIds as $id) {
                if (! is_numeric($id)) {
                    continue;
                }

                // Fetch raw message
                fwrite($fp, "A04 FETCH {$id} (BODY[])\r\n");
                $rawEmail = $this->readSocketFetchResponse($fp, 'A04');

                $parsed = $this->parseRawMimeMessage($rawEmail, (string) $id);
                if ($parsed) {
                    $messages[] = $parsed;
                }

                // Mark seen
                fwrite($fp, "A05 STORE {$id} +FLAGS (\\Seen)\r\n");
                $this->readSocketUntilTag($fp, 'A05');
            }

            // Logout
            fwrite($fp, "A06 LOGOUT\r\n");

            return $messages;
        } finally {
            @fclose($fp);
        }
    }

    protected function readSocketUntilTag($fp, string $tag): string
    {
        $buffer = '';
        while (! feof($fp)) {
            $line = fgets($fp, 4096);
            if ($line === false) {
                break;
            }
            $buffer .= $line;
            if (str_starts_with($line, "{$tag} ")) {
                break;
            }
        }

        return $buffer;
    }

    protected function readSocketFetchResponse($fp, string $tag): string
    {
        $buffer = '';
        while (! feof($fp)) {
            $line = fgets($fp, 8192);
            if ($line === false) {
                break;
            }
            $buffer .= $line;
            if (str_starts_with($line, "{$tag} OK") || str_starts_with($line, "{$tag} NO") || str_starts_with($line, "{$tag} BAD")) {
                break;
            }
        }

        return $buffer;
    }

    protected function parseRawMimeMessage(string $raw, string $id): ?IncomingEmailMessage
    {
        $parts = preg_split("/\r?\n\r?\n/", $raw, 2);
        $headerText = $parts[0] ?? '';
        $bodyText = $parts[1] ?? '';

        $subject = '(Sem assunto)';
        if (preg_match('/^Subject:\s*(.*)$/mi', $headerText, $m)) {
            $subject = trim($m[1]);
        }

        $senderEmail = 'desconhecido@email.com';
        $senderName = null;
        if (preg_match('/^From:\s*(.*)$/mi', $headerText, $m)) {
            $fromStr = trim($m[1]);
            if (preg_match('/(.*)<(.+@.+)>$/', $fromStr, $fromMatches)) {
                $senderName = trim(trim($fromMatches[1]), '"\'');
                $senderEmail = trim($fromMatches[2]);
            } else {
                $senderEmail = $fromStr;
            }
        }

        $date = new DateTimeImmutable;
        if (preg_match('/^Date:\s*(.*)$/mi', $headerText, $m)) {
            try {
                $date = new DateTimeImmutable(trim($m[1]));
            } catch (Exception) {
            }
        }

        $attachments = [];
        // Boundary extraction
        if (preg_match('/boundary=["\']?([^"\';\r\n]+)["\']?/i', $headerText, $bMatches)) {
            $boundary = $bMatches[1];
            $sections = explode("--{$boundary}", $bodyText);
            foreach ($sections as $section) {
                if (str_contains($section, 'filename=') || str_contains($section, 'name=')) {
                    $secParts = preg_split("/\r?\n\r?\n/", $section, 2);
                    $secHeader = $secParts[0] ?? '';
                    $secContent = $secParts[1] ?? '';

                    $fn = null;
                    if (preg_match('/filename=["\']?([^"\';\r\n]+)["\']?/i', $secHeader, $fnMatches)) {
                        $fn = trim($fnMatches[1]);
                    } elseif (preg_match('/name=["\']?([^"\';\r\n]+)["\']?/i', $secHeader, $fnMatches)) {
                        $fn = trim($fnMatches[1]);
                    }

                    if ($fn) {
                        $isBase64 = stripos($secHeader, 'Content-Transfer-Encoding: base64') !== false;
                        $content = $isBase64 ? base64_decode(str_replace(["\r", "\n", ' '], '', $secContent)) : $secContent;
                        $attachments[] = new IncomingEmailAttachment(
                            filename: $fn,
                            content: $content,
                            mimeType: 'application/octet-stream',
                            sizeBytes: strlen($content),
                        );
                    }
                }
            }
        }

        return new IncomingEmailMessage(
            messageId: $id,
            senderEmail: $senderEmail,
            senderName: $senderName,
            subject: $subject,
            bodyText: strip_tags($bodyText),
            date: $date,
            attachments: $attachments,
        );
    }
}
