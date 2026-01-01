<?php

class SMTP {
    private $connection;
    private $host;
    private $port;
    private $username;
    private $password;
    private $encryption;
    private $timeout = 30;
    private $debug = false;
    private $log = [];

    public function __construct($host, $port, $username, $password, $encryption = 'tls') {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->encryption = $encryption;
    }

    public function send($to, $subject, $body, $headers = []) {
        try {
            $this->connect();
            $this->auth();
            
            // From header is often required for the envelope
            $from = $this->getHeaderValue($headers, 'From');
            if (!$from) {
                // Fallback to username if email-like, or a dummy
                $from = filter_var($this->username, FILTER_VALIDATE_EMAIL) ? $this->username : 'noreply@' . $this->host;
            }
            // Extract email from "Name <email>" format if present
            if (preg_match('/<([^>]+)>/', $from, $matches)) {
                $fromEmail = $matches[1];
            } else {
                $fromEmail = $from;
            }

            $this->sendCommand('MAIL FROM: <' . $fromEmail . '>');
            $this->sendCommand('RCPT TO: <' . $to . '>');
            $this->sendCommand('DATA');

            // Construct raw email
            $raw = '';
            foreach ($headers as $key => $value) {
                // Skip From if we want to ensure it's set correctly, but usually we just append what's passed
                // Avoid duplicating headers if they are passed in $headers array keys or values
                if (is_string($key)) {
                    $raw .= "$key: $value\r\n";
                } else {
                    $raw .= "$value\r\n";
                }
            }
            if (!$this->getHeaderValue($headers, 'To')) {
                $raw .= "To: $to\r\n";
            }
            if (!$this->getHeaderValue($headers, 'Subject')) {
                $raw .= "Subject: $subject\r\n";
            }
            
            $raw .= "\r\n" . $body . "\r\n.";

            $this->sendCommand($raw);
            $this->sendCommand('QUIT');
            
            fclose($this->connection);
            return true;
        } catch (Exception $e) {
            if (is_resource($this->connection)) {
                fclose($this->connection);
            }
            throw $e;
        }
    }

    private function connect() {
        $protocol = '';
        if ($this->encryption === 'ssl') {
            $protocol = 'ssl://';
        }
        
        $this->connection = fsockopen($protocol . $this->host, $this->port, $errno, $errstr, $this->timeout);
        
        if (!$this->connection) {
            throw new Exception("Could not connect to SMTP host: $errno - $errstr");
        }

        $this->getResponse(); // Greeting
        $this->sendCommand('EHLO ' . gethostname());

        if ($this->encryption === 'tls') {
            $this->sendCommand('STARTTLS');
            if (!stream_socket_enable_crypto($this->connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("TLS negotiation failed");
            }
            $this->sendCommand('EHLO ' . gethostname());
        }
    }

    private function auth() {
        if ($this->username && $this->password) {
            $this->sendCommand('AUTH LOGIN');
            $this->sendCommand(base64_encode($this->username));
            $this->sendCommand(base64_encode($this->password));
        }
    }

    private function sendCommand($command) {
        fputs($this->connection, $command . "\r\n");
        return $this->getResponse();
    }

    private function getResponse() {
        $response = '';
        while (($line = fgets($this->connection, 515)) !== false) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') {
                break;
            }
        }
        
        // Simple error checking (codes 4xx and 5xx are errors)
        $code = substr($response, 0, 3);
        if ($code >= 400) {
            throw new Exception("SMTP Error: $response");
        }
        
        return $response;
    }

    private function getHeaderValue($headers, $name) {
        foreach ($headers as $key => $value) {
            // Handle ["From" => "val"] and ["From: val"]
            if (is_string($key) && strcasecmp($key, $name) === 0) {
                return $value;
            }
            if (is_int($key) && stripos($value, $name . ':') === 0) {
                return trim(substr($value, strlen($name) + 1));
            }
        }
        return null;
    }
}
