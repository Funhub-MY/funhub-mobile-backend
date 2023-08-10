<?php
namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class SecureHash {

    public static function hex($input) {
      
      $hex_table = array('0','1','2','3','4','5','6','7','8','9','A','B','C','D','E','F');
      
      $output = "";
      
      foreach($input as $byte) {
        $output .= $hex_table[($byte >> 4) & 0x0F];
        $output .= $hex_table[$byte & 0x0F]; 
      }
      
      return $output;
    }
    
    public static function generateSecureHash($originalString) {
      $hash = hash('sha256', $originalString); 
      return self::hex(str_split($hash));
    }
  }