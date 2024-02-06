<?php

/**
 * Prints the given data in a preformatted manner.
 *
 * @param mixed $data The data to be printed.
 */
function pre($data): void
{
    echo '<pre>';
    print_r($data);
    echo '</pre>';
}

/**
 * Encrypts or decrypts a given string using the AES-256-CBC encryption method.
 *
 * @param string $action The action to perform, either "encrypt" or "decrypt".
 * @param mixed  $string The string to encrypt or decrypt.
 *
 * @return false|string The encrypted or decrypted string, or false on failure.
 */
function encryptor(string $action, $string)
{
    $encrypt_method = 'AES-256-CBC';
    // pls set your unique hashing key
    $secret_key = 'WMS';
    $secret_iv  = 'wms123!';

    // hash
    $key = hash('sha256', $secret_key);

    // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
    $iv = substr(hash('sha256', $secret_iv), 0, 16);

    if ($action === 'encrypt') {
        $output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
        $output = base64_encode($output);
    } elseif ($action === 'decrypt') {
        $output = openssl_decrypt(base64_decode($string, true), $encrypt_method, $key, 0, $iv);
    } else {
        return false; // Invalid action
    }

    return $output !== false ? $output : false;
}

/**
 * Formats the response data based on the provided status code, list of data, and optional message.
 *
 * @param int          $status The status code indicating the result of the operation.
 * @param string       $msg    Optional message to be included in the response.
 * @param array|object $list   Optional list of data to be included in the response.
 *
 * @return array The formatted response data.
 */
function responseFormater(int $status, string $msg = '', object|array $list = []): array
{
    $data = [
        'status'  => $status,
        'error'   => ($status !== 200) ? $status : 0,
        'message' => $msg,
    ];

    if (! empty($list)) {
        $data['data'] = $list;
    }

    return $data;
}

/**
 * Returns the current date and time in the specified format.
 *
 * @param string $format The desired format of the date and time. Default is 'Y-m-d H:i:s'.
 *
 * @return string The formatted date and time.
 */
function getDateTime(string $format = 'Y-m-d H:i:s'): string
{
    return (new DateTime())->format($format);
}

/**
 * Returns the current date in the specified format.
 *
 * @param string $format The desired format of the date. Default is 'Y-m-d'.
 *
 * @return string The formatted date.
 */
function getTodayDate($format = 'Y-m-d'): string
{
    return getDateTime($format);
}
/**
 * Returns the current time in the specified format.
 *
 * @param string $format The desired format of the time. Default is 'H:i:s'.
 *
 * @return string The formatted time.
 */
function getTodayTime($format = 'H:i:s'): string
{
    return getDateTime($format);
}
