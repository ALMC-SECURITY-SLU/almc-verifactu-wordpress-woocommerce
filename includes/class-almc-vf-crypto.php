<?php
/**
 * ALMC Electronic Invoicing for VeriFactu — at-rest crypto for sensitive options.
 *
 * Encrypts a handful of credentials before they touch the wp_options table:
 *   - almc_vf_api_key
 *   - almc_vf_api_secret
 *   - almc_vf_webhook_secret
 *
 * Mechanism: libsodium authenticated encryption (XSalsa20-Poly1305 via
 * sodium_crypto_secretbox) with a 32-byte key derived from wp_salt('auth')
 * via HKDF-SHA256. The nonce is a fresh 24-byte random per write.
 *
 * Storage format on disk:   "vfenc1:" . base64(nonce || ciphertext)
 *
 * Reads return the plaintext transparently through the `option_*` filter,
 * so calling code (Settings, Api_Client, Webhook_Handler) does NOT need to
 * know that encryption is happening. The presence of the "vfenc1:" prefix
 * is what tells the read path "this value is encrypted"; legacy plaintext
 * values (saved by v1.0.x) are still readable, and a future write will
 * upgrade them in place.
 *
 * Security trade-off documented honestly:
 * If an attacker can read the DB AND read the WP filesystem (where the
 * salt lives), encryption gains nothing. But the threat model we DO defend
 * against is realistic: a DB-only leak via SQLi in a sibling plugin, a
 * stolen backup dump, a logging mishap that captured wp_options rows.
 * In those scenarios, the ciphertext is useless without the salt.
 *
 * @package ALMC_Electronic_Invoicing_VeriFactu
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ALMC_VF_Crypto {

    /**
     * Storage prefix that marks an encrypted blob.
     */
    const PREFIX = 'vfenc1:';

    /**
     * Options that get transparently encrypted at rest.
     */
    const PROTECTED_OPTIONS = array(
        'almc_vf_api_key',
        'almc_vf_api_secret',
        'almc_vf_webhook_secret',
    );

    /**
     * Wire up the filters that encrypt-on-write and decrypt-on-read.
     */
    public static function init() {
        if ( ! self::is_supported() ) {
            return;
        }
        foreach ( self::PROTECTED_OPTIONS as $opt ) {
            add_filter( 'pre_update_option_' . $opt, array( __CLASS__, 'filter_pre_update' ), 10, 1 );
            add_filter( 'pre_add_option_' . $opt,    array( __CLASS__, 'filter_pre_update' ), 10, 1 );
            add_filter( 'option_' . $opt,            array( __CLASS__, 'filter_get_option' ), 10, 1 );
        }
    }

    /**
     * Encrypt a non-empty string before it hits wp_options.
     *
     * @param mixed $value Incoming value from update_option/add_option.
     * @return mixed
     */
    public static function filter_pre_update( $value ) {
        if ( ! is_string( $value ) || '' === $value ) {
            return $value;
        }
        // Don't double-encrypt.
        if ( self::looks_encrypted( $value ) ) {
            return $value;
        }
        $enc = self::encrypt( $value );
        return false === $enc ? $value : $enc;
    }

    /**
     * Decrypt on read, returning plaintext to callers transparently.
     *
     * @param mixed $value Raw value as stored in wp_options.
     * @return mixed
     */
    public static function filter_get_option( $value ) {
        if ( ! is_string( $value ) || ! self::looks_encrypted( $value ) ) {
            return $value;
        }
        $plain = self::decrypt( $value );
        return false === $plain ? $value : $plain;
    }

    /**
     * Encrypt a plaintext string.
     *
     * @param string $plaintext
     * @return string|false Encrypted blob with PREFIX, or false on failure.
     */
    public static function encrypt( $plaintext ) {
        if ( ! self::is_supported() || ! is_string( $plaintext ) ) {
            return false;
        }
        try {
            $key   = self::derive_key();
            $nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
            $ct    = sodium_crypto_secretbox( $plaintext, $nonce, $key );
        } catch ( \Throwable $e ) {
            return false;
        }
        if ( false === $ct ) {
            return false;
        }
        return self::PREFIX . base64_encode( $nonce . $ct );
    }

    /**
     * Decrypt a string previously produced by self::encrypt().
     *
     * @param string $stored Stored value (with PREFIX).
     * @return string|false Plaintext, or false on failure.
     */
    public static function decrypt( $stored ) {
        if ( ! self::is_supported() || ! self::looks_encrypted( $stored ) ) {
            return false;
        }
        $blob = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true );
        if ( false === $blob || strlen( $blob ) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + 1 ) {
            return false;
        }
        $nonce = substr( $blob, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
        $ct    = substr( $blob, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
        try {
            $plain = sodium_crypto_secretbox_open( $ct, $nonce, self::derive_key() );
        } catch ( \Throwable $e ) {
            return false;
        }
        return false === $plain ? false : $plain;
    }

    /**
     * Whether the host has the libsodium bindings we need.
     */
    public static function is_supported() {
        return function_exists( 'sodium_crypto_secretbox' )
            && function_exists( 'sodium_crypto_secretbox_open' )
            && function_exists( 'hash_hkdf' )
            && function_exists( 'random_bytes' )
            && defined( 'SODIUM_CRYPTO_SECRETBOX_NONCEBYTES' )
            && defined( 'SODIUM_CRYPTO_SECRETBOX_KEYBYTES' );
    }

    /**
     * Recognise the storage marker.
     *
     * @param mixed $value
     * @return bool
     */
    public static function looks_encrypted( $value ) {
        return is_string( $value ) && 0 === strpos( $value, self::PREFIX );
    }

    /**
     * Derive the 32-byte symmetric key from wp_salt('auth') via HKDF-SHA256.
     *
     * Why HKDF and not raw wp_salt: salts can be longer or shorter than the
     * 32 bytes secretbox needs, and they were not designed as a KDF input.
     * HKDF gives us a uniformly-distributed 32-byte key regardless of salt
     * length / entropy distribution.
     *
     * @return string 32-byte binary key.
     */
    private static function derive_key() {
        $salt = wp_salt( 'auth' );
        // Domain separation: this constant is the "info" argument to HKDF so
        // the same wp_salt cannot be reused as a key for some other purpose.
        return hash_hkdf( 'sha256', $salt, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, 'almc-vf:option-encryption:v1', '' );
    }
}
