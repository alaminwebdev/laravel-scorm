@php
    // Fetch user tracking
    $userTrack = $tracking[$sco->id] ?? null;

    // Status & score
    $status = $userTrack->status ?? ($userTrack->completion_status ?? 'not_attempted');
    $score = $userTrack->score ?? '-';
    $color = match ($status) {
        'completed', 'passed' => 'green',
        'failed' => 'red',
        'incomplete' => 'yellow',
        default => 'gray',
    };

    // Handle prerequisites (if defined)
    $disabled = false;
    if (isset($sco->prerequisite_sco_id)) {
        $prereqStatus = $tracking[$sco->prerequisite_sco_id]->completion_status ?? 'not_attempted';
        if ($prereqStatus !== 'completed') {
            $disabled = true;
        }
    }
@endphp

<li class="pl-{{ $level * 5 }} border rounded p-3 bg-gray-50 hover:bg-gray-100 transition">
    <div class="flex justify-between items-center">
        <a href="{{ $disabled ? '#' : route('scorm.launch', $package->id) . '?sco=' . $sco->id }}" class="{{ $disabled ? 'text-gray-400 cursor-not-allowed' : 'text-blue-600 hover:underline' }}">
            {{ $sco->title }}
        </a>
        <span class="text-{{ $color }}-600 text-sm">
            {{ ucfirst($status) }} | Score: {{ $score }}
        </span>
    </div>

    @if ($sco->children && $sco->children->isNotEmpty())
        <ul class="mt-2 space-y-1">
            @foreach ($sco->children as $child)
                @include('scorm.partials.sco_item', ['sco' => $child, 'package' => $package, 'tracking' => $tracking, 'level' => $level + 1])
            @endforeach
        </ul>
    @endif
</li>
