<?php
namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class SecureHash {
    // This is an array for creating hex chars
    const HEX_TABLE = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B', 'C', 'D', 'E', 'F');

    /**
     * This method performs an arrangement with the byte array data, convert it into hexadecimal format and returns the final string.
     *
     * @param $input - the byte array of the initial string
     * @return string - the string for the resulting hash value
     */
    static function hex($input) {
        // create a StringBuffer 2x the size of the hash array
        $sb = '';
        // retrieve the byte array data, convert it to hex and add it to the StringBuffer
        for ($i = 0; $i < count($input); $i++) {
            $sb .= self::HEX_TABLE[($input[$i] >> 4) & 0xf];
            $sb .= self::HEX_TABLE[$input[$i] & 0xf];
        }
        return $sb;
    }

    /**
     * This method will return a string of hash value using the original.
     *
     * @param $originalString - the original string that will be used to produce the final hash value.
     * @return string - the string for the resulting hash value
     */
    public static function generateSecureHash($originalString) {
        $md = null;
        $ba = null;
        
        // create the md hash and ISO-8859-1 encode it
        try {
            $md = hash('sha256', $originalString, true);
            $ba = array_values(unpack('C*', $md));
        } catch (Exception $e) {
            // won't happen
        }
        return self::hex($ba);
    }
}