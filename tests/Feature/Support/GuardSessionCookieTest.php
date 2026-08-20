<?php

/**
 * Backend_schema.md §11.1: three cookie names, one per guard, alongside the
 * provider-level scoping already covered by GuardScopingTest. A distinct
 * cookie name is defense in depth — the actual security boundary is the
 * scoped provider — but matters most while agent/staff share a host with
 * the applicant portal (no subdomain separation yet in every environment).
 */
test('a request to the default (applicant) host gets the applicant session cookie', function () {
    $response = $this->get('/');

    $response->assertCookie('visa_applicant_session');
});

test('a request to the configured agent domain gets the agent session cookie', function () {
    $response = $this->get('http://'.config('app.agent_domain').'/');

    $response->assertCookie('visa_agent_session');
});

test('a request to the configured staff domain gets the staff session cookie', function () {
    $response = $this->get('http://'.config('app.staff_domain').'/');

    $response->assertCookie('visa_staff_session');
});
