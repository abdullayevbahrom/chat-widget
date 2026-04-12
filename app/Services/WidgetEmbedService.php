<?php

namespace App\Services;

use App\Models\Project;

class WidgetEmbedService
{
    /**
     * Generate a simple embed script HTML.
     *
     * Produces a simple script tag with no parameters.
     * Domain validation happens server-side via Origin/Referer headers.
     */
    public function generateEmbedScript(Project $project): string
    {
        $appUrl = rtrim(config('app.url'), '/');

        return "<script src=\"{$appUrl}/widget.js\" async defer></script>";
    }
}
