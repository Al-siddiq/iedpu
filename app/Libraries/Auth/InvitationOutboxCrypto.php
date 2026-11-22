<?php
declare(strict_types=1);
namespace App\Libraries\Auth;
use RuntimeException;
final class InvitationOutboxCrypto {
 private static function key(): string {
  $encoded=(string)env('IEDPU_INVITATION_OUTBOX_KEY','');
  $key=base64_decode($encoded,true);
  if (!is_string($key) || strlen($key)!==SODIUM_CRYPTO_SECRETBOX_KEYBYTES) throw new RuntimeException('Invitation outbox encryption key missing or invalid');
  return $key;
 }
 public static function seal(string $token): string {
  $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
  return base64_encode($nonce.sodium_crypto_secretbox($token,$nonce,self::key()));
 }
 public static function open(string $sealed): string {
  $bytes=base64_decode($sealed,true);
  if (!is_string($bytes)||strlen($bytes)<SODIUM_CRYPTO_SECRETBOX_NONCEBYTES+SODIUM_CRYPTO_SECRETBOX_MACBYTES) throw new RuntimeException('Invalid sealed invitation');
  $nonce=substr($bytes,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
  $token=sodium_crypto_secretbox_open(substr($bytes,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),$nonce,self::key());
  if (!is_string($token)) throw new RuntimeException('Invitation decryption failed');
  return $token;
 }
}
