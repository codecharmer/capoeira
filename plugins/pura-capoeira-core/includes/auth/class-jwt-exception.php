<?php
/**
 * Thrown by Pura\Core\Auth\Jwt on malformed tokens, keys or signatures.
 *
 * Callers catch it to answer 401 instead of leaking an unexpected 500.
 *
 * @package Pura\Core
 */

declare( strict_types=1 );

namespace Pura\Core\Auth;

defined( 'ABSPATH' ) || exit;

final class Jwt_Exception extends \RuntimeException {}
