<?php

namespace App\Controller\Concern;

use App\Service\AuthService;
use Symfony\Component\HttpFoundation\Request;

/**
 * Reusable session-based admin authorization, matching the project's
 * existing `auth_user` session pattern (see AdminController / StatisticsController).
 *
 * Use in controllers that need to gate access without depending on the
 * Symfony security firewall (which is not wired up for app login).
 */
trait AdminGuardTrait
{
    /**
     * Returns true only when the current session user is an authenticated admin.
     */
    private function isSessionAdmin(Request $request, AuthService $authService): bool
    {
        $sessionUser = $request->getSession()->get('auth_user');

        if (!is_array($sessionUser) || !isset($sessionUser['id'])) {
            return false;
        }

        // Trust the cached flag if present, otherwise verify against the DB.
        if (($sessionUser['is_admin'] ?? false) === true) {
            return true;
        }

        return $authService->isAdmin((int) $sessionUser['id']);
    }
}
