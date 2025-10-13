@extends('layouts.app')

@section('title', $package->title . ' Outline')

@section('content')
    <div class="bg-white shadow-md rounded-lg p-6">
        <h1 class="text-2xl font-bold mb-4">{{ $package->title }} 📚</h1>

        @if ($package->scos->isEmpty())
            <p class="text-gray-500">No SCOs found in this package.</p>
        @else
            <ul class="space-y-2">
                @foreach ($package->scos->whereNull('parent_id') as $sco)
                    @include('scorm.partials.sco_item', ['sco' => $sco, 'package' => $package, 'tracking' => $tracking, 'level' => 0])
                @endforeach
            </ul>
        @endif
    </div>
@endsection
