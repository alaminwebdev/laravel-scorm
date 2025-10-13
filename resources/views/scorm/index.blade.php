@extends('layouts.app')

@section('title', 'SCORM Management')

@section('content')
    <div class="space-y-6">

        <!-- Import SCORM File Section -->
        <div class="bg-white shadow-md rounded-lg p-6 border border-gray-200">
            <h1 class="text-xl font-bold mb-4">📥 Import SCORM File</h1>

            @if (session('success'))
                <div class="bg-green-100 text-green-700 p-3 rounded mb-4">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="bg-red-100 text-red-700 p-3 rounded mb-4">{{ session('error') }}</div>
            @endif

            <form action="{{ route('scorm.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div class="w-full">
                    <label class="block text-gray-700 font-medium text-sm mb-1">Select SCORM ZIP file</label>

                    <div class="flex items-center">
                        <!-- Choose File button on the LEFT -->
                        <button type="button" class="bg-blue-600 text-white px-4 py-2 rounded-l hover:bg-blue-700 transition-colors cursor-pointer" onclick="document.getElementById('scorm_file').click()">
                            Choose File
                        </button>

                        <!-- Display filename on the RIGHT -->
                        <div class="flex-1 border border-gray-300 rounded-r p-2 bg-white">
                            <span id="file_name" class="text-gray-500 text-sm">No file chosen</span>
                        </div>

                        <!-- Hidden file input -->
                        <input type="file" name="scorm_file" id="scorm_file" accept=".zip" required class="hidden" onchange="document.getElementById('file_name').textContent = this.files[0]?.name || 'No file chosen'">
                    </div>

                    <!-- Validation Criteria -->
                    <p class="text-gray-500 text-sm mt-1">
                        Accepted file: ZIP containing SCORM 1.2 or 2004 content.<br>
                        Must include <code>imsmanifest.xml</code> in the root.<br>
                        Maximum size: 50MB.<br>
                        Supported content: HTML, video, quizzes, assignments.
                    </p>
                </div>

                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors cursor-pointer">
                    Import
                </button>
            </form>
        </div>

        <!-- Uploaded SCORM Packages Section -->
        <div class="bg-white shadow-md rounded-lg p-6 border border-gray-200">
            <h2 class="text-xl font-semibold mb-4">📚 Imported SCORM Files</h2>

            @if ($packages->isEmpty())
                <p class="text-gray-500">No SCORM files imported yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm border-collapse border border-gray-200">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="border px-3 py-2 text-left">Title</th>
                                <th class="border px-3 py-2 text-left">Version</th>
                                <th class="border px-3 py-2 text-center">Launch</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($packages as $pkg)
                                <tr class="hover:bg-gray-50">
                                    <td class="border px-3 py-2">{{ $pkg->title }}</td>
                                    <td class="border px-3 py-2">{{ $pkg->version }}</td>
                                    <td class="border px-3 py-2 text-center">
                                        <a href="{{ route('scorm.launch', $pkg->id) }}" target="_blank" class="text-blue-600 hover:underline">Launch</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>
@endsection
