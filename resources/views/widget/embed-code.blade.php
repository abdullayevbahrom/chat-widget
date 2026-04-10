@props(['project' => null, 'widgetKey' => null])

@if ($project === null || $widgetKey === null)
    <p class="text-gray-500">Generate a widget key to get the embed code.</p>
@else
    <div class="space-y-2">
        @if (session('just_generated_' . $project->id))
            <p class="text-green-600 font-semibold">✓ Yangi widget key yaratildi! Ushbu kodni saytingizga nusxalang:</p>
            @php
                session()->forget('just_generated_' . $project->id);
            @endphp
        @endif

        @php
            $widgetJsUrl = url('/widget.js');
            $embedCode = <<<HTML
<script
    src="{$widgetJsUrl}"
    data-widget-key="{$widgetKey}"
    async
></script>
HTML;
        @endphp

        <pre class="bg-gray-100 dark:bg-gray-800 p-3 rounded text-sm overflow-x-auto"><code>{{ htmlspecialchars($embedCode) }}</code></pre>

        @if (session('just_generated_' . $project->id))
            <p class="text-amber-600 text-sm">⚠️ Bu keyni faqat bir marta ko'rasiz. Uni xavfsiz joyda saqlang!</p>
        @else
            <p class="text-gray-500 text-sm">Widget key faol. Yangi embed kodini ko'rish uchun yangi key yarating.</p>
        @endif
    </div>
@endif
