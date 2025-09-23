<?php

namespace App\Utils;

class PhoneUtility
{
    /**
     * Format phone number to international format with country code
     * 
     * @param string $phoneNumber The phone number to format
     * @param string $countryCode The country code (default: 234 for Nigeria)
     * @return string The formatted phone number with + prefix
     */
    public static function formatToInternational($phoneNumber, $countryCode = '234')
    {
        // Remove any non-digit characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // If empty after cleaning, return as is
        if (empty($phoneNumber)) {
            return $phoneNumber;
        }
        
        // Check if the number starts with '0' (local format)
        if (substr($phoneNumber, 0, 1) === '0') {
            // Replace the leading '0' with country code
            $phoneNumber = $countryCode . substr($phoneNumber, 1);
        }
        
        // If doesn't start with country code, add it
        // But first check if it's not already in international format
        if (substr($phoneNumber, 0, strlen($countryCode)) !== $countryCode) {
            $phoneNumber = $countryCode . $phoneNumber;
        }
        
        // Add '+' prefix if not already present
        if (substr($phoneNumber, 0, 1) !== '+') {
            $phoneNumber = '+' . $phoneNumber;
        }
        
        return $phoneNumber;
    }
    
    /**
     * Format phone number for WhatsApp via Twilio
     * 
     * @param string $phoneNumber The phone number to format
     * @param string $countryCode The country code (default: 234 for Nigeria)
     * @return string The formatted phone number with whatsapp: prefix
     */
    public static function formatForWhatsApp($phoneNumber, $countryCode = '234')
    {
        $formattedNumber = self::formatToInternational($phoneNumber, $countryCode);
        
        // Add WhatsApp prefix for Twilio
        if (!empty($formattedNumber) && substr($formattedNumber, 0, 9) !== 'whatsapp:') {
            return 'whatsapp:' . $formattedNumber;
        }
        
        return $formattedNumber;
    }
    
    /**
     * Format phone number for SMS via Twilio
     * 
     * @param string $phoneNumber The phone number to format
     * @param string $countryCode The country code (default: 234 for Nigeria)
     * @return string The formatted phone number for SMS
     */
    public static function formatForSMS($phoneNumber, $countryCode = '234')
    {
        return self::formatToInternational($phoneNumber, $countryCode);
    }
    
    /**
     * Validate if a phone number appears to be valid
     * 
     * @param string $phoneNumber The phone number to validate
     * @return bool Whether the phone number appears valid
     */
    public static function isValidPhoneNumber($phoneNumber)
    {
        // Remove any non-digit characters except the + prefix
        $cleaned = preg_replace('/[^0-9\+]/', '', $phoneNumber);
        
        // Check if we have at least 10 digits (minimum for most countries)
        return strlen(preg_replace('/[^0-9]/', '', $cleaned)) >= 10;
    }
}