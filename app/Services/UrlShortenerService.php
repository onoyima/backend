<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class UrlShortenerService
{
    /**
     * Generate a short URL for the given long URL
     *
     * @param string $longUrl
     * @param string $prefix
     * @return string
     */
    public function shortenUrl($longUrl, $prefix = 'ex')
    {
        // Generate a short code
        $shortCode = $this->generateShortCode($prefix);

        // Store the mapping in cache for 30 days
        Cache::put('short_url:' . $shortCode, $longUrl, now()->addDays(30));

        // Return the short URL
        return url('/s/' . $shortCode);
    }

    /**
     * Resolve a short code to its original URL
     *
     * @param string $shortCode
     * @return string|null
     */
    public function resolveUrl($shortCode)
    {
        return Cache::get('short_url:' . $shortCode);
    }

    /**
     * Generate a unique short code
     *
     * @param string $prefix
     * @return string
     */
    private function generateShortCode($prefix = 'ex')
    {
        do {
            // Generate a 6-character random string
            $randomString = Str::random(6);
            $shortCode = $prefix . $randomString;
        } while (Cache::has('short_url:' . $shortCode));

        return $shortCode;
    }

    /**
     * Create shortened URLs for parent consent
     *
     * @param string $consentToken
     * @return array
     */
    public function createConsentUrls($consentToken)
    {
        $approveUrl = url('/api/parent/consent/' . $consentToken . '/approve');
        $rejectUrl = url('/api/parent/consent/' . $consentToken . '/decline');

        return [
            'approve' => $this->shortenUrl($approveUrl, 'ap'),
            'reject' => $this->shortenUrl($rejectUrl, 'rj'),
            'approve_full' => $approveUrl,
            'reject_full' => $rejectUrl
        ];
    }
}
