@extends('layouts.app')

@section('title', 'Dashboard')

@section('header', 'Dashboard')

@section('content')
    <div class="space-y-6">
        <div class="bg-white shadow-md rounded-lg p-6">
            <p class="text-gray-800 font-medium">You're logged in!</p>
        </div>

        <div class="bg-white shadow-md rounded-lg p-6">
            <a href="{{ route('scorm.index') }}" class="inline-block bg-blue-600 text-white px-6 py-3 rounded hover:bg-blue-700 transition-colors">
                Go to SCORM Management
            </a>
        </div>
    </div>
@endsection
