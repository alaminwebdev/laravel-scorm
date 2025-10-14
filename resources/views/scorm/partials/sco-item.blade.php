@php
    $hasChildren = isset($sco->children) && $sco->children->count() > 0;
    $padding = ($level ?? 0) * 20;
    $launchPath = $sco->is_launchable ?? false ? $sco->launch ?? ($package->entry_point ?? 'index.html') : null;
@endphp

<li>
    <div class="sco-item flex items-center p-3 rounded-lg border border-transparent transition-colors cursor-pointer text-gray-700 hover:bg-gray-50" data-sco-id="{{ $sco->id }}" data-launch="{{ $launchPath }}"
        style="padding-left: {{ $padding + 12 }}px;">
        @if ($hasChildren)
            <span class="mr-2">📁</span>
        @else
            <span class="mr-2">📄</span>
        @endif

        <span class="sco-title flex-1 truncate">{{ $sco->title }}</span>
    </div>

    @if ($hasChildren)
        <ul class="mt-1 space-y-1 ml-4 child-items">
            @foreach ($sco->children as $child)
                @include('scorm.partials.sco-item', ['sco' => $child, 'level' => ($level ?? 0) + 1, 'package' => $package])
            @endforeach
        </ul>
    @endif
</li>
