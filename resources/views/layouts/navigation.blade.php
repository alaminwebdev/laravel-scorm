<header class="bg-white shadow p-4 mb-6">
    <div class="max-w-7xl mx-auto flex justify-between items-center">
        <!-- Logo -->
        <div class="flex items-center space-x-4">
            <a href="{{ route('dashboard') }}" class="text-xl font-bold text-gray-800">
                Laravel LMS
            </a>

            <!-- Primary Navigation -->
            <nav class="hidden sm:flex space-x-4">
                <a href="{{ route('dashboard') }}" class="px-3 py-2 rounded-md text-gray-700 hover:bg-gray-100 @if (request()->routeIs('dashboard')) bg-gray-200 @endif">
                    Dashboard
                </a>
                <a href="{{ route('scorm.index') }}" class="px-3 py-2 rounded-md text-gray-700 hover:bg-gray-100 @if (request()->routeIs('scorm.index')) bg-gray-200 @endif">
                    SCORM
                </a>
            </nav>
        </div>

        <!-- User Dropdown -->
        <div class="hidden sm:flex sm:items-center relative">
            <button id="userMenuButton" class="flex items-center px-3 py-2 border border-transparent rounded-md text-gray-500 hover:text-gray-700 focus:outline-none">
                {{ Auth::user()->name }}
                <svg class="ml-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </button>

            <div id="userMenuDropdown" class="hidden absolute right-0 top-10 mt-2 w-48 bg-white border rounded-md shadow-lg py-1 z-50">
                <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Profile</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full text-left px-4 py-2 text-gray-700 hover:bg-gray-100">Log Out</button>
                </form>
            </div>
        </div>

        <!-- Hamburger for Mobile -->
        <div class="sm:hidden">
            <button id="mobileMenuButton" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path id="hamburgerIcon" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div id="mobileMenu" class="hidden sm:hidden mt-2 space-y-1 px-4 pb-4">
        <a href="{{ route('dashboard') }}" class="block px-3 py-2 rounded-md text-gray-700 hover:bg-gray-100">Dashboard</a>
        <a href="{{ route('scorm.index') }}" class="block px-3 py-2 rounded-md text-gray-700 hover:bg-gray-100">SCORM</a>
        <div class="border-t border-gray-200 pt-2 mt-2">
            <a href="{{ route('profile.edit') }}" class="block px-3 py-2 rounded-md text-gray-700 hover:bg-gray-100">Profile</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full text-left px-3 py-2 rounded-md text-gray-700 hover:bg-gray-100">Log Out</button>
            </form>
        </div>
    </div>
</header>

<script>
    // Toggle user dropdown
    document.getElementById('userMenuButton')?.addEventListener('click', function() {
        const menu = document.getElementById('userMenuDropdown');
        menu.classList.toggle('hidden');
    });

    // Toggle mobile menu
    document.getElementById('mobileMenuButton')?.addEventListener('click', function() {
        const menu = document.getElementById('mobileMenu');
        menu.classList.toggle('hidden');
    });
</script>
