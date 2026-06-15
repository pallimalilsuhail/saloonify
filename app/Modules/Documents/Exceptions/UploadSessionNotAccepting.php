<?php

declare(strict_types=1);

namespace App\Modules\Documents\Exceptions;

use RuntimeException;

/**
 * Raised when an attempt is made to upload to a session that is not in
 * the Active state (expired / submitted / revoked) or whose token cannot
 * be matched. The presign controller maps this to a 410 Gone — the
 * customer needs a fresh link from the agent.
 */
final class UploadSessionNotAccepting extends RuntimeException {}
