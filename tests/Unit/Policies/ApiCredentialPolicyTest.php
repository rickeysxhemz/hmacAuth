<?php

declare(strict_types=1);

use HmacAuth\Policies\ApiCredentialPolicy;
use HmacAuth\Tests\Fixtures\Models\User;
use Illuminate\Auth\Access\Response;

describe('ApiCredentialPolicy', function () {
    beforeEach(function () {
        $this->policy = new ApiCredentialPolicy;
        $this->user = new User([
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    });

    describe('viewAny', function () {
        it('allows authenticated users to view any credentials', function () {
            $response = $this->policy->viewAny($this->user);

            expect($response)->toBeInstanceOf(Response::class)
                ->and($response->allowed())->toBeTrue();
        });
    });

    describe('view', function () {
        it('allows authenticated users to view a credential', function () {
            $response = $this->policy->view($this->user);

            expect($response)->toBeInstanceOf(Response::class)
                ->and($response->allowed())->toBeTrue();
        });
    });

    describe('create', function () {
        it('allows authenticated users to create credentials', function () {
            $response = $this->policy->create($this->user);

            expect($response)->toBeInstanceOf(Response::class)
                ->and($response->allowed())->toBeTrue();
        });
    });

    describe('update', function () {
        it('allows authenticated users to update credentials', function () {
            $response = $this->policy->update($this->user);

            expect($response)->toBeInstanceOf(Response::class)
                ->and($response->allowed())->toBeTrue();
        });
    });

    describe('delete', function () {
        it('allows authenticated users to delete credentials', function () {
            $response = $this->policy->delete($this->user);

            expect($response)->toBeInstanceOf(Response::class)
                ->and($response->allowed())->toBeTrue();
        });
    });

    describe('rotateSecret', function () {
        it('allows authenticated users to rotate secrets', function () {
            $response = $this->policy->rotateSecret($this->user);

            expect($response)->toBeInstanceOf(Response::class)
                ->and($response->allowed())->toBeTrue();
        });
    });

    describe('toggleStatus', function () {
        it('allows authenticated users to toggle credential status', function () {
            $response = $this->policy->toggleStatus($this->user);

            expect($response)->toBeInstanceOf(Response::class)
                ->and($response->allowed())->toBeTrue();
        });
    });

    describe('viewLogs', function () {
        it('allows authenticated users to view logs', function () {
            $response = $this->policy->viewLogs($this->user);

            expect($response)->toBeInstanceOf(Response::class)
                ->and($response->allowed())->toBeTrue();
        });
    });
});
