<?php

declare(strict_types=1);

namespace HmacAuth\Policies;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Authorization policy for API credential management.
 *
 * This is a basic policy that allows authenticated users to manage credentials.
 * Override this policy in your application for custom authorization logic.
 */
class ApiCredentialPolicy
{
    /**
     * Determine if the user can view any credentials.
     */
    public function viewAny(Authenticatable $user): Response
    {
        return $this->authorizeUser($user);
    }

    /**
     * Determine if the user can view a specific credential.
     */
    public function view(Authenticatable $user): Response
    {
        return $this->authorizeUser($user);
    }

    /**
     * Determine if the user can create credentials.
     */
    public function create(Authenticatable $user): Response
    {
        return $this->authorizeUser($user);
    }

    /**
     * Determine if the user can update credentials.
     */
    public function update(Authenticatable $user): Response
    {
        return $this->authorizeUser($user);
    }

    /**
     * Determine if the user can delete credentials.
     */
    public function delete(Authenticatable $user): Response
    {
        return $this->authorizeUser($user);
    }

    /**
     * Determine if the user can rotate a credential's secret.
     */
    public function rotateSecret(Authenticatable $user): Response
    {
        return $this->authorizeUser($user);
    }

    /**
     * Determine if the user can toggle credential status.
     */
    public function toggleStatus(Authenticatable $user): Response
    {
        return $this->authorizeUser($user);
    }

    /**
     * Determine if the user can view credential logs.
     */
    public function viewLogs(Authenticatable $user): Response
    {
        return $this->authorizeUser($user);
    }

    /**
     * Base authorization check.
     *
     * Override this method to implement custom authorization logic.
     * For example, check for specific roles or permissions:
     *
     *     return $user->hasRole('admin')
     *         ? Response::allow()
     *         : Response::deny('Unauthorized');
     */
    protected function authorizeUser(Authenticatable $user): Response
    {
        // Default: allow all authenticated users
        // Override this method in your application for custom logic
        return Response::allow();
    }
}
