<?php

namespace WPIDE\App\Controllers\Snippets;

use WPIDE\App\Kernel\Request;
use WPIDE\App\Kernel\Response;
use WPIDE\App\Services\Snippets\Capabilities;
use WPIDE\App\Services\Snippets\Plans;

/**
 * Shared helpers for the Snippets controllers: JSON body as arrays and capability checks.
 */
trait JsonInput
{
    protected function body(Request $request): array
    {
        $data = json_decode((string) $request->getContent(), true);

        return is_array($data) ? $data : [];
    }

    protected function allowed(Response $response, string $capability): bool
    {
        if (current_user_can($capability)) {
            return true;
        }

        $response->json(__('You are not allowed to do this.', 'wpide'), 403);

        return false;
    }

    /**
     * Pro features answer 403 on a site without WPIDE Pro (the free version, or no usable license).
     */
    protected function pro(Response $response): bool
    {
        if (Plans::pro()) {
            return true;
        }

        $response->json(__('This feature needs WPIDE Pro.', 'wpide'), 403);

        return false;
    }

    /**
     * A feature that starts at a higher plan: refused with the plan's name, so the screen can say what to
     * upgrade to.
     */
    protected function plan(Response $response, string $feature): bool
    {
        if (Plans::allows($feature)) {
            return true;
        }

        $plan = Plans::feature($feature);
        $response->json(['message' => sprintf(__('This is part of WPIDE %s and up.', 'wpide'), $plan['plan']), 'code' => 'plan_upgrade', 'tier' => $plan['tier'], 'plan' => $plan['plan']], 403);

        return false;
    }

    /**
     * Any snippet editing capability (the type-level check happens in the service).
     */
    protected function allowedAny(Response $response): bool
    {
        if (current_user_can(Capabilities::EDIT_TEXT) || current_user_can(Capabilities::ACTIVATE)) {
            return true;
        }

        $response->json(__('You are not allowed to do this.', 'wpide'), 403);

        return false;
    }
}
