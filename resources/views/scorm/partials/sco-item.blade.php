@php
    $hasChildren = isset($sco->children) && $sco->children->count() > 0;
    $isLaunchable = $sco->is_launchable ?? false;
    $padding = ($level ?? 0) * 20;
    $launchPath = $isLaunchable ? $sco->launch ?? ($package->entry_point ?? 'index.html') : '';
@endphp

<li>
    <div class="text-sm sco-item flex items-center p-3 rounded-lg border border-transparent transition-colors {{ $isLaunchable ? 'cursor-pointer hover:bg-gray-200' : 'cursor-default' }} text-gray-700"
        @if ($isLaunchable) data-sco-id="{{ $sco->id }}" data-launch="{{ $launchPath }}" @endif style="padding-left: {{ $padding + 12 }}px;">

        @if ($hasChildren)
            <span class="mr-2">📁</span>
        @else
            <span class="mr-2">
                @if ($isLaunchable)
                    🚀
                @else
                    📄
                @endif
            </span>
        @endif

        <span class="sco-title flex-1 truncate text-sm">
            {{ $sco->title }}
            @if (!$isLaunchable && !$hasChildren)
                <span class="text-xs text-gray-400 ml-2">(Asset)</span>
            @endif
        </span>
        <div class="badge-container ml-2 text-sm" data-sco-id="{{ $sco->id }}">
            <!-- Badge will be inserted here by JavaScript -->
        </div>
    </div>

    @if ($hasChildren)
        <ul class="mt-1 space-y-1 ml-4 child-items">
            @foreach ($sco->children as $child)
                @include('scorm.partials.sco-item', ['sco' => $child, 'level' => ($level ?? 0) + 1, 'package' => $package])
            @endforeach
        </ul>
    @endif
</li>
