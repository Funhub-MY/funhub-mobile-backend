<?php
namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class SecureHash {
    /**
     * This method will return a string of hash value using the original.
     *
     * @param $originalString - the original string that will be used to produce the final hash value.
     * @return string - the string for the resulting hash value
     */
    public static function generateSecureHash($originalString) {
        return strtoupper(hash('sha256', $originalString)); 
    }
}