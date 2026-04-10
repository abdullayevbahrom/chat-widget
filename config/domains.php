<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Domain Validation Regex
    |--------------------------------------------------------------------------
    |
    | Strict domain regex that prevents invalid formats like "example..com",
    | requires at least one dot, valid chars only, no consecutive dots,
    | and a valid TLD (2+ chars).
    |
    | Used by: TenantResolver, TenantDomainController, TenantForm, and other
    | components that need domain validation.
    |
    */

    'regex' => '/^(?!-)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.(?!-)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*\.[a-z]{2,}$/i',
];
