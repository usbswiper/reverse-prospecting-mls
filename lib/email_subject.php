<?php

/**
 * Guards the email subject as it crosses into preview, queue, and delivery.
 *
 * The extension is responsible for decoding a mailto subject into plain text
 * before it reaches this service. Validating here keeps a stale extension build
 * or a future upstream markup change from previewing, queueing, or sending an
 * encoded, control-character, markup, or script-wrapper subject.
 */
class EmailSubject
{
    /**
     * @param mixed  $value
     * @param string $label
     * @param bool   $allowEmpty
     * @return string
     * @throws Exception
     */
    public static function assertSafe($value, $label = 'Email subject', $allowEmpty = false)
    {
        if (!is_string($value)) {
            throw new Exception($label . ' must be plain text.');
        }

        if ($value === '') {
            if ($allowEmpty) {
                return $value;
            }
            throw new Exception($label . ' is required.');
        }

        // Control characters would corrupt or inject into the mail header.
        if (preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new Exception($label . ' contains unsupported control characters.');
        }

        // Percent-encoding means the extension did not decode the mailto subject.
        if (preg_match('/%[0-9a-f]{2}/i', $value)) {
            throw new Exception($label . ' must be decoded before previewing or queueing.');
        }

        // Markup, script wrappers, or a persisted wpdb placeholder token all mean
        // the value is not the plain-text subject we expect.
        if (preg_match('/[<>]/', $value)
            || preg_match('/(?:javascript\s*:|window\s*\.\s*(?:top\s*\.\s*)?open\s*\(|return\s+false\b)/i', $value)
            || preg_match('/\{[a-f0-9]{64}\}/i', $value)) {
            throw new Exception($label . ' contains unsupported markup or link-wrapper text.');
        }

        return $value;
    }

    /**
     * Validate and HTML-escape a subject for safe reflection in preview HTML.
     *
     * @param mixed $value
     * @return string
     * @throws Exception
     */
    public static function escapeForHtml($value)
    {
        return htmlspecialchars(self::assertSafe($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Validate the optional prefix and subject, then the combined header value.
     *
     * @param mixed $prefix
     * @param mixed $subject
     * @return string
     * @throws Exception
     */
    public static function compose($prefix, $subject)
    {
        self::assertSafe($prefix, 'Subject prefix', true);
        self::assertSafe($subject);

        return self::assertSafe($prefix . $subject);
    }

    /**
     * Replace the "MLS No: X" token with a custom MLS number, keeping the result
     * a validated plain-text subject.
     *
     * @param mixed $subject
     * @param mixed $customMlsNumber
     * @return string
     * @throws Exception
     */
    public static function replaceMlsNumber($subject, $customMlsNumber)
    {
        self::assertSafe($subject);
        self::assertSafe($customMlsNumber, 'Custom MLS number');

        // preg_replace_callback avoids interpreting the custom value as a
        // replacement backreference (e.g. "$1" or "\\0").
        $replaced = preg_replace_callback('/MLS No:\s*\w+/', function () use ($customMlsNumber) {
            return 'MLS No: ' . $customMlsNumber;
        }, $subject);

        return self::assertSafe($replaced);
    }
}
