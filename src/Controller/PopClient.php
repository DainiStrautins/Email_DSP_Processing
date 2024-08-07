<?php

namespace Email\Controller;

use DateTime;
use InvalidArgumentException;

/**
 * Class Pop3Client
 *
 * This class represents a POP3 email client with various methods for connecting, retrieving emails,
 * and filtering them based on an allowed list.
 *
 * @package YourNamespace\YourPackage
 */
class PopClient {

    /** @var resource|null The POP3 server socket. */
    private $socket;

    /** @var array Configuration options for the POP3 client. */
    private array $config;

    /** @var array An array to store processed emails. */
    private array $processedEmails = [];

    /** @var array A property to store global email headers. */
    private array $globalEmails = [];



    private array $finalizedJsonStructureArray = [];
    const CRLF = "\r\n";

    private string $processedEmailsJson;

    private string $configPath;

    private string $dataPath;

    private string $emlPath;

    private string $excelPath;

    private mixed $globalConfig; // Global variable to store the decoded data

    private bool $connected = false; // Add a flag to track the connection status

    private string $customConfigPath;

    /**
     * Pop3Client constructor.
     *
     * @param array $config The configuration settings for the POP3 client.
     *
     * Initializes the Pop3Client with configuration.
     */
    public function __construct(
        array $config,
        ?string $configPath,
        ?string $dataPath,
        ?string $emlPath,
        ?string $excelPath,
        ?string $customConfigPath
    )
    {
        $this->config = $config;
        $this->getPaths($configPath, $dataPath, $emlPath, $excelPath, $customConfigPath);

        $this->ensurePathsExist();
        $this->ensureConfigExists();

        // Load and store the config data globally
        $this->globalConfig = $this->loadConfig();

        $this->loadProcessedEmails();
    }

    /**
     * Pop3Client destructor.
     *
     * Closes the connection to the POP3 server when the instance is destroyed.
     */
    public function __destruct()
    {
        $this->close(); // Close the connection in the destructor
    }

    /**
     * Establishes a secure TLS connection to the POP3 server using the configured credentials.
     *
     * This method checks if the configuration is using default values for the POP3 connection.
     * If default values are detected, it sets the connection status to false and outputs a warning message.
     * Otherwise, it attempts to create a TLS socket connection to the configured POP3 server.
     *
     * @return bool True if the connection is successful; otherwise, false.
     *
     */
    public function connect(): bool
    {
        // Check if the configuration is using default values
        if (
            $this->globalConfig['login']['hostname'] === 'yourhost.name.com' &&
            $this->globalConfig['login']['port'] === 995 &&
            $this->globalConfig['login']['username'] === 'test@test.com' &&
            $this->globalConfig['login']['password'] === 'yourPasswordToEmailServer'
        ) {
            $this->connected = false;
            echo "<br>You are currently using default values, so please make sure you change those to connect to your own server";
            // Configuration is using default values, skip the connection
            return false;
        }

        $this->socket = stream_socket_client('tls://' . $this->globalConfig['login']['hostname'] . ':' . $this->globalConfig['login']['port'], $errno, $errstr, 60);
        if (!$this->socket) {
            die("Unable to connect to the POP3 server. Error: $errno - $errstr");
        }

        $this->connected = true;
        return true;
    }

    /**
     * Logs in to the POP3 server with the provided username and password.
     *
     * @return void
     */
    public function login(): void
    {
        if (!$this->connected)
        {
            return;
        }
        fwrite($this->socket, "USER {$this->globalConfig['login']['username']}". self::CRLF);
        fgets($this->socket); // Read and discard the server's response
        fwrite($this->socket, "PASS {$this->globalConfig['login']['password']}". self::CRLF);
        fgets($this->socket); // Read and discard the server's response
    }

    /**
     * Sends a command to the POP3 server.
     *
     * @param string $command The command to send.
     *
     * @return void
     */
    public function sendCommand(string $command): void
    {
        fwrite($this->socket, $command . self::CRLF);
    }

    /**
     * Reads a line of data from the POP3 server.
     *
     * @return string The line of data read from the server.
     */
    public function readLine(): string
    {
        return fgets($this->socket);
    }

    /**
     * Closes the connection to the POP3 server.
     *
     * @return void
     */
    public function close(): void {
        if (is_resource($this->socket)) {
            fwrite($this->socket, "QUIT" . self::CRLF);
            fclose($this->socket);
        }
    }

    /**
     * Loads and decodes the configuration data from the configuration file.
     *
     * @return array The decoded configuration data, or an empty array if the file is empty or does not exist.
     */
    private function loadConfig(): mixed
    {
        $configData = file_get_contents($this->configPath . '/mail_configurations.json');
        return json_decode($configData, true) ?? [];
    }

    /**
     * Sets the paths for configuration, data, email, and Excel directories.
     *
     * @param string|null $configPath      The path to the configuration directory.
     * @param string|null $dataPath        The path to the data directory.
     * @param string|null $emlPath         The path to the email directory.
     * @param string|null $excelPath       The path to the Excel directory.
     * @param string|null $customConfigPath The path to the custom configuration file.
     *
     * @return void
     */
    private function getPaths(
        ?string $configPath = null,
        ?string $dataPath = null,
        ?string $emlPath = null,
        ?string $excelPath = null,
        ?string $customConfigPath = null
    ): void {
        $this->configPath = $configPath ?? __DIR__ . '/../../config';
        $this->dataPath = $dataPath ?? __DIR__ . '/../../data';
        $this->emlPath = $emlPath ?? __DIR__ . '/../../emails';
        $this->excelPath = $excelPath ?? __DIR__ . '/../../excels';
        $this->processedEmailsJson = $this->dataPath . '/processed_emails.json';
        $this->customConfigPath = $customConfigPath ?? __DIR__ . '/custom_config.json';
    }

    private function ensurePathsExist(): void
    {
        // Create the configuration directory if it doesn't exist
        if (!file_exists($this->configPath)) {
            mkdir($this->configPath, 0755, true);
        }

        // Create the data directory if it doesn't exist
        if (!file_exists($this->dataPath)) {
            mkdir($this->dataPath, 0755, true);
        }

        // Create the data directory if it doesn't exist
        if (!file_exists($this->excelPath)) {
            mkdir($this->excelPath, 0755, true);
        }

        // Create the data directory if it doesn't exist
        if (!file_exists($this->emlPath)) {
            mkdir($this->emlPath, 0755, true);
        }
        // Create the processed_emails.json file if it doesn't exist
        if (!file_exists($this->processedEmailsJson)) {
            file_put_contents($this->processedEmailsJson, null);
        }

        // Create the data directory if it doesn't exist
        $dataDirectory = dirname($this->processedEmailsJson);
        if (!file_exists($dataDirectory)) {
            mkdir($dataDirectory, 0755, true);
        }
    }

    /**
     * Ensures that the mail_configurations.json file exists and contains valid configuration data.
     * If not, it creates or updates the file with default or custom configuration data.
     *
     * @return void
     */
    private function ensureConfigExists(): void
    {
        $configFile = $this->configPath . '/mail_configurations.json';

        // Check if the config file exists
        if (file_exists($configFile)) {
            $configData = file_get_contents($configFile);

            if (!empty($configData)) {
                // Config file exists and has data
                return;
            }
        }

        // Check if a custom configuration file path is provided
        if ($this->customConfigPath !== null) {
            // Check if the custom configuration file exists
            if (file_exists($this->customConfigPath)) {
                // Load and decode the custom configuration
                $customConfig = json_decode(file_get_contents($this->customConfigPath), true);

                // Check if the custom configuration is empty
                if (!empty($customConfig)) {
                    echo "<br>Using custom configuration from '{$this->customConfigPath}'.";
                    $config = $customConfig;
                } else {
                    echo "<br>Custom configuration file is empty. Using default configuration.";
                    $config = $this->getDefaultConfig();
                }
            } else {
                echo "<br>Custom configuration file isn't found. Using the default configuration.";
                $config = $this->getDefaultConfig();
            }
        } else {
            echo "<br>No custom configuration path provided. Using the default configuration.";
            $config = $this->getDefaultConfig();
        }

        $this->close();

        // Save the merged or default configuration to the config file
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
    }

    /**
     * Returns the default configuration data.
     *
     * @return array The default configuration data.
     */
    private function getDefaultConfig(): array
    {
        return [
            "login" => [
                "hostname" => "yourhost.name.com",
                "port" => 995,
                "username" => "test@test.com",
                "password" => "yourPasswordToEmailServer"
            ],
            "whitelist" => [
                "senders" => ["test@test.com"],
                "receivers" => ["test@test.com"]
            ],
            "headers_to_filter" => [
                "From",
                "Return-Path",
                "To",
                "Date",
                "Subject",
                "Delivered-To"
            ]
        ];
    }
    /**
     * Loads processed emails from a JSON file into the client.
     *
     * @return void
     */
    private function loadProcessedEmails(): void {
        $processedEmailsJson = file_get_contents($this->processedEmailsJson);
        $this->processedEmails = json_decode($processedEmailsJson, true) ?: [];
    }



    /**
     * Lists all emails on the POP3 server.
     *
     * @return array An array of email information.
     */
    private function listAllEmails(): array
    {
        $emailList = [];

        $this->sendCommand("LIST" . self::CRLF);

        // Discard the response for the LIST command
        while ($line = $this->readLine()) {
            // The response should be in the format: "<email_number> <email_size>"
            if (preg_match('/^(\d+) (\d+)$/', trim($line), $matches)) {
                $emailList[] = [
                    'number' => $matches[1],
                    'size' => $matches[2],
                ];
            }

            if (trim($line) === '.') {
                break;
            }
        }

        return $emailList;
    }

    /**
     * Retrieves email headers for a list of emails.
     *
     * @param array $listOfEmails The list of emails to retrieve headers for.
     *
     * @return array An array of email headers.
     */
    private function getEmailHeaders(array $listOfEmails): array
    {
        $emailList = [];

        if (empty($listOfEmails)) {
            return $emailList; // No emails to process, return an empty array
        }

        // Use the TOP command to retrieve headers for all emails
        $emailCount = count($listOfEmails);
        $emailSizes = array_column($listOfEmails, column_key: 'size');

        for ($i = 1; $i <= $emailCount; $i++) {
            $this->sendCommand("TOP $i 0" . self::CRLF);
            $headers = '';
            while ($line = $this->readLine()) {
                if (trim($line) === '-ERR unimplemented' || trim($line) === '+OK') {
                    // Skip this line
                    continue;
                }
                $headers .= $line;
                if (trim($line) === '.') {
                    break;
                }
            }

            $emailList[] = [
                'number' => $i,
                'read_date' => date("d.m.Y H:i:s"),
                'header' => [
                    'raw' => $headers,
                    'size' => $emailSizes[$i - 1], // Use $i - 1 to match the email size with the correct email number
                ],

            ];
        }

        return $emailList; // Return the email headers without setting them globally
    }

    /**
     * Sets Email Headers so its used widely else where
     *
     * @param array $listOfEmails
     * @return void
     */
    private function setGlobalEmailHeaders(array $listOfEmails): void
    {
        // Call getEmailHeaders to retrieve email headers and set them globally
        $this->globalEmails = $this->getEmailHeaders($listOfEmails);
    }

    /**
     * Checks if a string starts with a given prefix.
     *
     * @param string $haystack The string to check.
     * @param string $needle The prefix to search for.
     *
     * @return bool True if the string starts with the given prefix; otherwise, false.
     */
    public function startsWith(string $haystack, string $needle): bool
    {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }

    /**
     * Extracts the sender email address from a given email header line.
     *
     * @param string $line The email header line to extract the sender from.
     *
     * @return string The extracted sender email address, or an empty string if not found.
     */
    private function extractSenderFromHeader(string $line): string
    {
        $sender = '';

        // Check for known header types
        if ($this->startsWith($line, 'From:') || $this->startsWith($line, 'Return-Path:')) {
            // Extract sender from the "From:" or "Return-Path:" header
            $potentialSender = trim(substr($line, strpos($line, ':') + 1));

            // Check if potentialSender is a valid email address and not empty
            if (filter_var($potentialSender, FILTER_VALIDATE_EMAIL) && !empty($potentialSender)) {
                $sender = $potentialSender;
            }
        }

        return $sender;
    }

    /**
     * Extracts the receiver email addresses from a given email header line.
     *
     * @param string $line The email header line to extract receivers from.
     *
     * @return array An array of extracted receiver email addresses, or an empty array if none are found.
     */
    private function extractReceiversFromHeader(string $line): array
    {
        $receivers = [];

        // Check for the "To:" header to extract receiver(s)
        if ($this->startsWith($line, 'To:')) {
            // Extract the raw receiver string from the "To:" header
            $receiverString = trim(substr($line, strlen('To:')));

            // Split the receiver string into individual email addresses using commas as the separator
            $receiverArray = explode(',', $receiverString);

            // Trim and validate each email address and add it to the receiver array
            foreach ($receiverArray as $receiver) {
                $receiver = trim($receiver);

                // Check if the receiver is a valid email address and not empty
                if (filter_var($receiver, FILTER_VALIDATE_EMAIL) && !empty($receiver)) {
                    $receivers[] = $receiver;
                }
            }
        }

        return $receivers;
    }

    /**
     * Checks if an email is in the whitelist based on its headers.
     *
     * @param string $headers The email headers to check.
     * @param array $whitelist The whitelist configuration.
     *
     * @return bool True if the email is in the whitelist, otherwise, false.
     */
    private function isEmailInWhitelist(string $headers, array $whitelist): bool
    {
        // Initialize sender and receiver variables
        $sender = '';
        $receivers = [];

        // Split the headers into lines
        $headerLines = explode("\r\n", $headers);

        // Iterate through header lines to find sender and receiver
        foreach ($headerLines as $line) {
            $line = trim($line); // Trim leading/trailing spaces

            // Extract sender from the header
            $potentialSender = $this->extractSenderFromHeader($line);
            if (!empty($potentialSender)) {
                $sender = $potentialSender;
            }

            // Extract receivers from the header
            $receiverArray = $this->extractReceiversFromHeader($line);
            if (!empty($receiverArray)) {
                $receivers = array_merge($receivers, $receiverArray);
            }

            // If both sender and receiver are found, break out of the loop
            if ($sender && !empty($receivers)) {
                break;
            }
        }

        // Convert whitelist values to lowercase for case-insensitive comparison
        $senderInWhitelist = in_array(strtolower($sender), array_map('strtolower', $whitelist['senders']));
        $receiversInWhitelist = array_intersect(array_map('strtolower', $receivers), array_map('strtolower', $whitelist['receivers']));

        // Check if the sender and at least one receiver are both non-empty and in the whitelist
        if ($sender && !empty($receivers) && $senderInWhitelist && !empty($receiversInWhitelist)) {
            return true; // Both sender and at least one receiver are in the whitelist
        }

        return false; // Email does not match whitelist criteria
    }

    /**
     * Filters emails down to those in the whitelist.
     *
     * @param array $listOfEmails The list of emails to filter.
     *
     * @return array An array of filtered emails.
     */
    public function filterDownToWhiteListEmails(array $listOfEmails): array
    {
        // Ensure that globalEmails is populated with email headers
        if (empty($this->globalEmails)) {
            $this->setGlobalEmailHeaders($listOfEmails);
        }

        $filteredEmails = [];


        // Iterate through globalEmails and filter emails based on whitelist criteria
        foreach ($this->globalEmails as $email) {
            $headers = $email['header']['raw'];
            // Check if the email matches the whitelist criteria (from and to addresses)
            if ($this->isEmailInWhitelist($headers, $this->globalConfig['whitelist'])) {
                $filteredEmails[] = $email;
            }
        }
        return $filteredEmails;
    }

    /**
     * Checks if an email hash exists in the loaded processed emails.
     *
     * @param string $hash The email hash to check.
     *
     * @return bool True if the hash exists, false otherwise.
     */
    private function emailHashExists(string $hash): bool
    {
        // Check if the hash exists in the loaded processed emails
        return isset($this->processedEmails[$hash]);
    }

    /**
     * Processes an email and generates a unique hash for it based on its content or headers.
     *
     * @param array $email An array representing the email.
     *
     * @return array An array containing the email hash and is_duplicate_header status.
     */
    private function processEmail(array $email): array
    {
        // Generate a unique hash for the email based on its content or headers
        // Replace this with your logic to generate the hash
        $hash = md5(json_encode($email['header']));

        // Check if the email hash already exists in processed_emails.json
        $isDuplicate = $this->emailHashExists($hash);

        return [
            'header' => [
                'hash' => $hash,
                'is_duplicate_header' => $isDuplicate ? 'true' : 'false',
            ]
        ];
    }

    /**
     * Generates an array of unique hashes and their is_duplicate_header status for a list of filtered emails.
     *
     * This function processes each filtered email and generates a unique hash for it.
     *
     * @param array $filteredEmails An array of filtered emails to generate hashes for.
     *
     * @return void
     */
    public function generateFilteredEmailHash(array $filteredEmails): void
    {
        foreach ($filteredEmails as &$filteredEmail) {
            $processedEmail = $this->processEmail($filteredEmail);
            $filteredEmail['header']['hash'] = $processedEmail['header']['hash'];
            $filteredEmail['header']['is_duplicate_header'] = $processedEmail['header']['is_duplicate_header'];
        }

        // Update the globalEmails array with the processed emails
        $this->globalEmails = $filteredEmails;
    }

    /**
     * Deletes emails marked as duplicates from the global emails array.
     *
     * @return void
     */
    private function deleteDuplicateEmailsFromGlobalVariable(): void
    {
        $filteredEmails = array_filter($this->globalEmails, function ($email) {
            return $email['header']['is_duplicate_header'] === 'false';
        });

        $this->globalEmails = array_values($filteredEmails);
    }

    /**
     * Filter the raw headers of emails in globalEmails based on a configuration
     *
     * This function iterates through globalEmails and filters the raw headers
     * based on the provided configuration. It checks if the headers match the
     * specified headers to filter and trims header values. Additionally, it checks
     * the "Return-Path" header to ensure it contains a valid email address before
     * including it.
     *
     *
     * @return void
     */
    private function filterEmailHeaders(): void
    {
        // Ensure that globalEmails is not empty
        if (empty($this->globalEmails)) {
            return;
        }

        // Iterate through globalEmails and filter the raw headers
        foreach ($this->globalEmails as &$email) {
            if (isset($email['header']['raw'])) {
                $filteredHeaders = [];
                $rawHeaders = explode(PHP_EOL, $email['header']['raw']);

                foreach ($rawHeaders as $header) {
                    $headerParts = explode(':', $header, 2);
                    if (count($headerParts) === 2) {
                        $headerName = trim($headerParts[0]);
                        $headerValue = trim($headerParts[1]); // Trim the header value

                        if ($headerName === 'Return-Path') {
                            // Check if the header value is a valid email address
                            if (filter_var($headerValue, FILTER_VALIDATE_EMAIL)) {
                                $filteredHeaders[] = $header;
                            }
                        } elseif (in_array($headerName, $this->globalConfig['headers_to_filter'])) {
                            $filteredHeaders[] = $header;
                        }
                    }
                }

                $email['header']['raw'] = implode(PHP_EOL, $filteredHeaders);
            }
        }
    }

    /**
     * Retrieve the content of non-duplicate emails from the global emails.
     *
     * This function retrieves the content of emails marked as non-duplicate from the global emails array.
     *
     * @param array $globalEmails An array of email information, including the "is_duplicate_header" flag.
     *
     * @return array An array of non-duplicate email content, each element containing the email number and content.
     */
    private function retrieveEachEmailContent(array $globalEmails): array
    {
        // Retrieve email content here
        $nonDuplicateEmailContent = [];

        // Loop through globalEmails and find non-duplicate emails
        foreach ($globalEmails as $emailIndex => $email) {
            $emailNumber = $email['number'];
            $this->sendCommand("RETR $emailNumber" . self::CRLF);

            $emailContent = '';
            while ($line = $this->readLine()) {
                if (trim($line) === '-ERR unimplemented' || trim($line) === '+OK') {
                    // Skip this line
                    continue;
                }
                $emailContent .= $line;
                if (trim($line) === '.') {
                    break; // End of email
                }
            }

            // Check if the email content has .xls, .xlsx, or .csv attachments
            $hasAttachment = preg_match('/Content-Disposition:\s*attachment;\s*filename="[^"]+\.(xlsx|xls|csv)"/i', $emailContent);

            if ($hasAttachment) {
                $nonDuplicateEmailContent[$emailNumber] = [
                    'number' => $emailNumber,
                    'content' => $emailContent,
                ];
            } else {
                // Unset the email if it doesn't have the specified attachments
                unset($globalEmails[$emailIndex]);
            }
        }

        // Re-index the array keys to remove gaps
        $this->globalEmails = array_values($globalEmails);

        return $nonDuplicateEmailContent;
    }

    /**
     * Adds to the global emails array email body content and hashes based on provided data.
     *
     * @param array $emailWithBody An array containing email body content and associated data.
     *
     * @return void
     */
    private function addBodyToGlobalEmailsStructure(array $emailWithBody): void
    {
        foreach ($this->globalEmails as &$email) {
            $numberFromGlobalEmails = $email['number'];
            $email['body']['hash'] = md5($emailWithBody[$numberFromGlobalEmails]['content']);

            // Update the raw field with the email content from $emailWithBody
            $email['body']['raw'] = $emailWithBody[$numberFromGlobalEmails]['content'];
        }
    }


    /**
     * Reindex the globalEmails array by email hash and update the global variable.
     *
     * This function reindex-es the globalEmails array by the email hash and updates
     * the globalEmails class variable with the reindex-ed array.
     *
     * @return void
     */
    private function reindexGlobalEmailsByHash(): void
    {
        $newGlobalEmails = [];

        foreach ($this->globalEmails as $email) {
            $hash = $email['header']['hash'];
            $newGlobalEmails[$hash] = $email;
        }

        $this->globalEmails = $newGlobalEmails;
    }

    /**
     * Get the MIME type based on a file extension.
     *
     * This function maps commonly used file extensions to their corresponding MIME types.
     *
     * @param string $extension The file extension (e.g., 'xlsx', 'xls').
     *
     * @return string The MIME type associated with the given extension.
     *
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/MIME_types
     */
    private function getMimeTypeFromExtension(string $extension): string
    {
        return match ($extension) {
            'xlsx', 'xls' => 'application/vnd.ms-excel',
            default => 'application/octet-stream',
        };
    }

    /**
     * Process email contents and associate attachments with each email based on its hash.
     *
     * This function also unsets all of those emails who don't have xlsx or xls attachment.
     *
     * @param array $emailContents An array of email contents.
     *
     * @return void
     */
    public function processEmailsWithAttachments(array $emailContents): void
    {
        // Track existing attachment hashes
        $existingAttachmentHashes = [];

        // Iterate through processed emails and extract existing attachment hashes
        foreach ($this->processedEmails as $emailData) {
            if (isset($emailData['attachments'])) {
                $existingAttachmentHashes = array_merge($existingAttachmentHashes, array_keys($emailData['attachments']));
            }
        }

        foreach ($emailContents as $emailHash => $email) {
            $emailContent = $email['body']['raw'];
            $hasValidAttachment = false;

            // Extract attachments
            if (preg_match_all('/Content-Disposition:\s*attachment;\s*filename="([^"]+)"/i', $emailContent, $matches)) {
                foreach ($matches[1] as $attachmentFilename) {
                    // Use multiple patterns to match different types of attachments
                    $patterns = [
                        "/Content-Disposition:\s*attachment;\s*filename=\"$attachmentFilename\".*?Content-Transfer-Encoding:\s*base64\s*(.*?)\r\n--/s",
                        '/Content-Type:\s*application\/(?:vnd\.ms-excel|octet-stream);\s*name="[^"]+"\s*.*?Content-Transfer-Encoding:\s*base64\s*(.*?)\r\n--/is'
                    ];

                    foreach ($patterns as $pattern) {
                        if (preg_match($pattern, $emailContent, $contentMatches)) {
                            $base64Content = trim($contentMatches[1]);

                            // Decode base64 content
                            $decodedContent = base64_decode($base64Content);

                            // Allowed extensions
                            $allowedExtensions = ['xlsx', 'xls', 'csv'];
                            $extension = strtolower(pathinfo($attachmentFilename, PATHINFO_EXTENSION));

                            if ($decodedContent === false) {
                                echo "Attachment '$attachmentFilename' could not be decoded.\n";
                            }

                            if (in_array($extension, $allowedExtensions) && $decodedContent !== false) {
                                $attachmentHash = md5($base64Content);

                                // Check if this attachment hash already exists
                                if (in_array($attachmentHash, $existingAttachmentHashes)) {
                                    // Set is_duplicate to the hash
                                    $isDuplicateHash = $attachmentHash;
                                } else {
                                    // Add the attachment hash to the list of existing hashes
                                    $existingAttachmentHashes[] = $attachmentHash;
                                    $isDuplicateHash = 'null';
                                }

                                if (!isset($this->globalEmails[$emailHash]['attachments'])) {
                                    $this->globalEmails[$emailHash]['attachments'] = [];
                                }

                                // Create a 'filename' based on the full attachment hash and the original extension
                                $filename = $attachmentHash . '.' . $extension;

                                // Store the filename, extension, and hash
                                $this->globalEmails[$emailHash]['attachments'][$attachmentHash] = [
                                    'filename' => $filename,
                                    "original_file_name" => $attachmentFilename,
                                    'extension' => $extension,
                                    'status' => [
                                        "duplicate" => ($isDuplicateHash === 'null') ? false : true
                                    ],
                                    'size' => strlen($decodedContent),
                                    'raw_base64' => $base64Content,
                                    'raw' => $decodedContent,
                                    'is_duplicate' => $isDuplicateHash,
                                    'mime' => $this->getMimeTypeFromExtension($extension),
                                ];

                                $hasValidAttachment = true;
                            }
                        }
                    }
                }
            }

            // Only unset the email if there are no valid attachments at all
            if (!$hasValidAttachment) {
                echo "Removing email with hash '$emailHash' because it has no valid attachments.\n";
                unset($this->globalEmails[$emailHash]);
            }
        }
    }

    /**
     * Download attachments from email data.
     *
     * This method iterates through the global emails and, if attachments are present,
     * initiates the download process using the EmailAttachmentDownloader class.
     *
     *
     * @param array $arrayOfEmails
     *
     * @return void
     */
    public function downloadEmailAttachments(array $arrayOfEmails): void
    {
        $attachmentDownloader = new EmailAttachmentDownloader;
        foreach ($arrayOfEmails as $emailData) {
            if (!empty($emailData['attachments'])) {
                $attachmentDownloader->downloadAttachments($emailData['attachments'],$this->excelPath);
            }
        }
    }

    /**
     * Save filtered emails to individual EML files.
     *
     * This method saves the filtered emails to individual EML files in the 'emails' folder. If an EML file with the same
     * hash already exists, it will not be overwritten.
     *
     * @param array $emails An array of filtered email contents.
     *
     * @return void
     */
    private function saveEMLFiles(array $emails): void
    {
        // Ensure the folder exists, or create it if necessary
        $emlFolderPath = rtrim($this->emlPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!is_dir($emlFolderPath)) {
            mkdir($emlFolderPath, 0755, true);
        }

        // Loop through the filtered emails and save each one to its own EML file
        foreach ($emails as $id => $emailContent) {
            // Create the EML file name
            $emlFileName = $emlFolderPath . $id . '.eml';

            // Check if the EML file already exists
            if (!file_exists($emlFileName)) {
                // Save the email content to the EML file
                file_put_contents($emlFileName, $emailContent['body']['raw']);
            }
        }
    }

    /**
     * Extracts Return-Path(s) from email content.
     *
     * @param string $emailContent The email content.
     *
     * @return array An array of Return-Path(s).
     */
    private function extractReturnPath(string $emailContent): array
    {
        $returnPaths = [];

        // Handle Return-Path without angle brackets
        if (preg_match_all('/Return-Path:\s*([^<\r\n]+)/m', $emailContent, $matches)) {
            foreach ($matches[1] as $returnPath) {
                $returnPaths[] = trim($returnPath);
            }
        }

        // Remove duplicate Return-Paths
        return array_unique($returnPaths);
    }

    /**
     * Extracts Delivered-To and To email addresses from email content.
     *
     * @param string $emailContent The email content.
     *
     * @return array An array of email addresses.
     */
    private function extractDeliveredToAndTo(string $emailContent): array
    {
        $emails = [];

        // Extract Delivered-To
        $deliveredToMatches = [];
        if (preg_match_all('/^Delivered-To:\s*([^<\r\n]+)/m', $emailContent, $deliveredToMatches)) {
            $deliveredToEmails = $deliveredToMatches[1];
            foreach ($deliveredToEmails as $email) {
                $emails[] = trim($email);
            }
        }

        // Extract To
        $toMatches = [];
        if (preg_match_all('/^To:\s*([^<\r\n]+)/m', $emailContent, $toMatches)) {
            $toEmails = $toMatches[1];
            foreach ($toEmails as $email) {
                // Split multiple addresses separated by commas
                $emailAddresses = explode(',', $email);
                foreach ($emailAddresses as $address) {
                    $emails[] = trim($address);
                }
            }
        }

        // Remove duplicate email addresses
        return array_unique($emails);
    }

    /**
     * Extracts the email subject from email content.
     *
     * @param string $emailContent The email content.
     *
     * @return string|null The email subject or null if not found.
     */
    function extractSubject(string $emailContent): ?string
    {
        if (preg_match('/^Subject:\s*(.*?)\s*$/m', $emailContent, $matches)) {
            return trim($matches[1]);
        }
        return null; // Return null if not found
    }

    /**
     * Extracts the formatted date from email content.
     *
     * @param string $emailContent The email content.
     *
     * @return string|null The formatted date (d.m.Y H:i:s) or null if not found or unable to parse.
     */
    private function extractDate(string $emailContent): ?string
    {
        if (preg_match('/Date:\s*(.*)\r\n/', $emailContent, $matches)) {
            $rawDate = $matches[1];
            $timestamp = strtotime($rawDate);
            if ($timestamp !== false) {
                return date('d.m.Y H:i:s', $timestamp);
            }
        }
        return null; // Return null if not found or unable to parse
    }

    /**
     * Extracts email headers and appends them to the global emails array.
     *
     * @param array $rawHeaders An array of raw email headers.
     *
     * @return void
     */
    private function extractEmailHeaders(array $rawHeaders): void
    {
        foreach ($rawHeaders as $header) {
            $emailHash = $header['header']['hash']; // Assuming 'hash' is the unique identifier

            $headerInfo = [
                'from' => $this->extractReturnPath($header['header']['raw']),
                'to' => $this->extractDeliveredToAndTo($header['header']['raw']),
                'subject' => $this->extractSubject($header['header']['raw']),
                'date' => $this->extractDate($header['header']['raw']),
            ];

            // Append the header information to the specific email
            $this->globalEmails[$emailHash]['emailInfo'] = $headerInfo;
        }
    }

    /**
     * Create a JSON Structure from Global Email Data.
     *
     * This method iterates through global email data and creates a structured array
     * suitable for JSON serialization, including email details and attachments information.
     *
     * @return void
     */
    private function createJsonStructure(): void
    {
        foreach ($this->globalEmails as $email) {
            $emailInfo = [
                "from" => $email['emailInfo']['from'],
                "to" => $email['emailInfo']['to'],
                "subject" => $email['emailInfo']['subject'],
                "date" => $email['emailInfo']['date'],
                "read_date" => $email['read_date'],
            ];

            // Check if there are attachments
            if (!empty($email['attachments'])) {
                $emailInfo['attachments'] = [];

                // Iterate through the "hash" keys to access attachment information
                foreach ($email['attachments'] as $hash => $attachmentInfo) {
                    $attachmentData = [
                        "filename" => $attachmentInfo['filename'],
                        "original_file_name" => $attachmentInfo['original_file_name'],
                        "extension" => $attachmentInfo['extension'],
                        "status" => $attachmentInfo['status'],
                        "size" => $attachmentInfo['size'],
                        'mime' => $attachmentInfo['mime'],
                        "is_duplicate" => $attachmentInfo['is_duplicate'],
                    ];

                    // Add the attachment data to the attachments' array
                    $emailInfo['attachments'][$hash] = $attachmentData;
                }
            }

            // Add the email info to the finalized JSON structure array
            $this->finalizedJsonStructureArray[$email['header']['hash']] = $emailInfo;
        }
    }

    /**
     * Save Processed Emails into a JSON File.
     *
     * This method merges the new processed emails with the existing processed emails
     * and saves the updated data into a JSON file for future reference.
     *
     * @param array $newEmailData An array containing the new processed email data to be added.
     *
     * @return void
     *
     */
    private function saveIntoJson(array $newEmailData): void
    {
        // Merge the new processed emails with the existing processed emails
        $this->processedEmails = array_merge($this->processedEmails, $newEmailData);

        $jsonEmailData = json_encode($this->processedEmails, JSON_PRETTY_PRINT);
        file_put_contents($this->processedEmailsJson, $jsonEmailData);
    }


    /**
     * Executes the core functionality of the POP3 client:
     *
     * 1. Retrieve email headers for all emails.
     * 2. Filter emails based on a whitelist.
     * 3. Remove duplicate emails.
     * 4. Filter email headers to minimize header content.
     * 5. Retrieve email bodies (including attachments).
     * 6. Close the connection.
     * 7. Add email bodies to the global email structure.
     * 8. Reindex global emails by hash.
     * 9. Process emails and prepare the structure to download and save into Json.
     * 10. Download all email attachments.
     * 11. Save .eml type file of each email.
     * 12. Extract email headers from global emails and make emailInfo array that stores it.
     * 13. Merge the new processed emails with the existing processed emails and save it into Json file.
     * @return void
     */
    public function coreEmailFunctionality(): void
    {
        if (!$this->connected) {
            return;
        }
        // 1. Retrieve email headers for all emails.
        $filteredEmails = $this->filterDownToWhiteListEmails($this->listAllEmails());


        // 2. Generate filtered email hashes.
        $this->generateFilteredEmailHash($filteredEmails);

        // 3. Remove duplicate emails.
        // This function also updates the globalEmails variable, making it later.
        $this->deleteDuplicateEmailsFromGlobalVariable();

        // 4. Filter email headers to minimize header content.
        $this->filterEmailHeaders();

        // 5. Retrieve email bodies (including attachments).
        $emailWithBody = $this->retrieveEachEmailContent($this->globalEmails);

        // 6. Close the connection since we do not need it anymore in this transaction.
        $this->close();

        if(empty($this->globalEmails)) {
            return;
        }

        // 7. Add email bodies to the global email structure.
        $this->addBodyToGlobalEmailsStructure($emailWithBody);

        // 8. Reindex global emails by hash.
        $this->reindexGlobalEmailsByHash();

        // 9. Process emails and prepare the structure to download and save into Json.
        $this->processEmailsWithAttachments($this->globalEmails);

        // 10. Download email attachments.
        $this->downloadEmailAttachments($this->globalEmails);

        // 11. Save .eml file for each email full response.
        $this->saveEMLFiles($this->globalEmails);

        // 12. Extract email headers from global emails.
        $this->extractEmailHeaders($this->globalEmails);

        // 13. Creates Json file structure that needs to be saved.
        $this->createJsonStructure();

        // 14. Saves the file into processed_emails.json the created structure variable
        $this->saveIntoJson($this->finalizedJsonStructureArray);
    }

    /**
     * Retrieves a list of Excel files from processed emails.
     *
     * This function searches through processed emails and collects the list of Excel files
     * found in email attachments.
     *
     * @return array An array of Excel files with their attachment data.
     */
    function getListOfExcelFiles(): array
    {
        $attachments = [];
        foreach ($this->processedEmails as $emailData) {
            // Check if there are attachments in this email
            if (isset($emailData['attachments'])) {
                // Add the entire 'attachments' section to the attachments' array
                $attachments[] = [
                    'subject' => $emailData['subject'],
                    'date' => $emailData['date'],
                    'read_date' => $emailData['read_date'],
                    $emailData['attachments'],
                ];
            }
        }

        return $attachments;
    }

    /**
     * Get attachments from emails sent in a specific year and month.
     *
     * This function retrieves attachments along with other fields (subject, date, read_date) from emails
     * sent in the specified year and month.
     *
     * @param int $year  The year (e.g., 2023).
     * @param int $month The month (1 to 12, e.g., 6 for June).
     *
     * @return array An array of email attachments that match the specified year and month.
     *               Each element of the array includes fields: 'subject', 'date', 'read_date', 'attachments'.
     *               If no matching emails are found, an empty array is returned.
     *
     * @throws InvalidArgumentException If the provided year or month is not valid.
     *
     * @see DateTime
     */
    public function getAttachmentsByYearAndMonth(int $year, int $month): array
    {
        $attachmentsByYearAndMonth = [];

        foreach ($this->processedEmails as $emailData) {
            // Check if the email has attachments and a valid date
            if (isset($emailData['attachments']) && isset($emailData['date'])) {
                $emailDate = DateTime::createFromFormat('d.m.Y H:i:s', $emailData['date']);

                // Check if the date was successfully parsed and matches the specified year and month
                if ($emailDate instanceof DateTime &&
                    $emailDate->format('Y') === (string)$year &&
                    $emailDate->format('n') === (string)$month
                ) {
                    // Include desired fields (subject, date, read_date, attachments) in the result
                    $attachmentsByYearAndMonth[] = [
                        'subject' => $emailData['subject'],
                        'date' => $emailData['date'],
                        'read_date' => $emailData['read_date'],
                        'attachments' => $emailData['attachments'],
                    ];
                }
            }
        }
        return $attachmentsByYearAndMonth;
    }

    /**
     * Updates attachment status by filenames.
     *
     * This function updates the status of attachments in processed emails based on
     * specified filenames and new status.
     *
     * @param array $fullFilenames An array of full filenames (with an extension and path).
     * @param array $newStatus The new status to set for the attachments status.
     *
     * @return void
     */
    public function updateAttachmentStatusByFilenames(array $fullFilenames, array $newStatus): void
    {
        // Load the JSON file that contains attachment information
        $attachments = $this->processedEmails;

        // Iterate through each full filename in the array
        foreach ($fullFilenames as $fullFilename) {
            // Extract the partial filename (remove the extension and any path)
            $partialFilename = pathinfo($fullFilename, PATHINFO_FILENAME);
            // Extract the extension from the full filename
            $extension = pathinfo($fullFilename, PATHINFO_EXTENSION);

            // Iterate through each email in the JSON
            foreach ($attachments as $emailId => $emailData) {
                // Check if the email has attachments
                if (isset($emailData['attachments'])) {
                    // Check if the partial filename exists in the attachments
                    if (isset($emailData['attachments'][$partialFilename])) {
                        // Check if the extension matches the JSON record
                        if ($emailData['attachments'][$partialFilename]['extension'] === $extension) {
                            // Update the status directly
                            $attachments[$emailId]['attachments'][$partialFilename]['status'] = array_merge(
                                $emailData['attachments'][$partialFilename]['status'],
                                $newStatus
                            );

                            break; // Stop searching after the first match is found
                        } else {
                            error_log( "Wrong attachments for $fullFilename." . PHP_EOL . "<br>");
                        }
                    }
                }
            }
        }

        // Save the updated attachment information back to the JSON file
        file_put_contents($this->processedEmailsJson, json_encode($attachments, JSON_PRETTY_PRINT));
    }

    /**
     * Retrieves base64 content for specified Excel files.
     *
     * This function takes an array of requested file names and retrieves the base64 content
     * of the specified Excel files from processed emails.
     *
     * @param array $fileNames An array of requested file names.
     *
     * @return array An array of base64 content for the requested Excel files.
     */
    function getExcelFilesBase64(array $fileNames): array
    {
        $base64Contents = [];
        foreach ($fileNames as $requestedFileName) {
            $base64Data = [];

            if ($requestedFileName) {
                // Get the email ID and original file name
                $emlFileName = $requestedFileName['key'];

                // Get the original file name to search in .eml file since there can be many files in attachment
                $originalFileName = $requestedFileName['original_file_name'];

                // Find the corresponding .eml file in the emails folder
                $emlFilePath = $this->emlPath . '/' . $emlFileName . '.eml';

                if (file_exists($emlFilePath)) {
                    // Read the .eml file and extract base64 content
                    $emlContent = file_get_contents($emlFilePath);

                    // Create a pattern to match the entire base64 content
                    $pattern = '/Content-Disposition: attachment;\s*filename="' . preg_quote($originalFileName, '/') . '".*?Content-Transfer-Encoding: base64(.*?)(?=\R--)/s';

                    if (preg_match($pattern, $emlContent, $matches)) {
                        $base64Data['key'] = $requestedFileName['key'];
                        $base64Data['original_file_name'] = $requestedFileName['original_file_name'];
                        $base64Data['filename'] = $requestedFileName['filename'];
                        $base64Data['raw'] = base64_decode(trim($matches[1]));
                    } else {
                        // Handle if base64 content extraction fails
                        $base64Data['key'] = $requestedFileName['key'];
                        $base64Data['original_file_name'] = $requestedFileName['original_file_name'];
                        $base64Data['filename'] = $requestedFileName['filename'];
                        $base64Data['raw'] = 'Base64 content not found in email';
                    }
                } else {
                    // Handle if .eml file doesn't exist
                    $base64Data['key'] = $requestedFileName['key'];
                    $base64Data['original_file_name'] = $requestedFileName['original_file_name'];
                    $base64Data['filename'] = $requestedFileName['filename'];
                    $base64Data['raw'] = 'Email file not found';
                }
            } else {
                // Handle if requested file name not found in processed emails
                $base64Data['filename'] = '';
                $base64Data['raw'] =  'File not found in processed emails';
            }

            $base64Contents[] = $base64Data;
        }

        return $base64Contents;
    }

    /**
     * Get original file names where Excel content doesn't match attachment keys.
     *
     * @param array $processedEmails An array of processed email data.
     * @return array An array of original file names.
     */
    function getMismatchedOriginalFileNames(array $processedEmails): array
    {
        $mismatchedOriginalFileNames = [];

        foreach ($processedEmails as $key => $emailData) {
            if (isset($emailData['attachments'])) {
                foreach ($emailData['attachments'] as $attachmentKey => $attachment) {
                    // Generate the expected Excel content hash
                    $expectedContentHash = $attachmentKey;

                    // Generate the actual Excel file path
                    $excelFilePath = $this->excelPath . '/' . $attachment['filename'];

                    // Check if the Excel file exists
                    if (file_exists($excelFilePath)) {
                        // Read the Excel file and calculate the content hash of the Excel file
                        $actualContentHash = md5(file_get_contents($excelFilePath));

                        // Check if the content hashes don't match
                        if ($expectedContentHash !== $actualContentHash) {
                            $mismatchedOriginalFileNames[] = [
                                "key" => $key,
                                "original_file_name" => $attachment['original_file_name'],
                                "filename" => $attachment['filename'],
                            ];
                        }
                    } else {
                        // If the Excel file doesn't exist, add information about it
                        $mismatchedOriginalFileNames[] = [
                            "key" => $key,
                            "original_file_name" => $attachment['original_file_name'],
                            "filename" => $attachment['filename'],
                        ];
                    }
                }
            }
        }

        return $mismatchedOriginalFileNames;
    }

    public function fixTheExcelFiles(): void {
        // Check if there are any processed emails
        if (empty($this->processedEmails)) {
            return; // Do nothing if no records are found
        }
        $filesToOverwrite = $this->getMismatchedOriginalFileNames($this->processedEmails);
        $base64 = $this->getExcelFilesBase64($filesToOverwrite);
        $attachmentDownloader = new EmailAttachmentDownloader;

        // Use the EmailAttachmentDownloader to overwrite the Excel files
        $attachmentDownloader->downloadAttachments($base64,$this->excelPath, true);
        // Set overwriting to true in case file exists
    }
}

