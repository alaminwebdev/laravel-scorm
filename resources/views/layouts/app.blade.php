<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Laravel LMS')</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-gray-100 min-h-screen">

    <!-- Header -->
    {{-- <header class="bg-white shadow p-4 mb-6">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold">@yield('header', 'Laravel LMS')</h1>
            <nav>
                <a href="{{ route('dashboard') }}" class="text-blue-600 hover:underline mr-4">Dashboard</a>
                <a href="{{ route('scorm.index') }}" class="text-blue-600 hover:underline">SCORM</a>
            </nav>
        </div>
    </header> --}}
    @include('layouts.navigation')


    <!-- Main Content -->
    <main class="max-w-7xl mx-auto">
        @yield('content')
    </main>

</body>
</html>
