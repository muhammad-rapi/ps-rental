<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aplikasi Rental PlayStation</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Inter dari Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }
        .container {
            max-width: 1200px;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">
    <header class="bg-white shadow-md py-4">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <h1 class="text-2xl font-bold text-gray-900">
                <a href="#">Rental PlayStation</a>
            </h1>
            <nav>
                <ul class="flex space-x-4">
                    <li><a href="/" class="text-gray-600 hover:text-indigo-600 font-medium">Beranda</a></li>
                    <li><a href="/perangkat" class="text-gray-600 hover:text-indigo-600 font-medium">Perangkat</a></li>
                    {{-- <li><a href="#" class="text-gray-600 hover:text-indigo-600 font-medium">Keluar</a></li> --}}
                </ul>
            </nav>
        </div>
    </header>

    <main class="py-10">
        @if (session('success'))
            <div class="container mx-auto px-4 mb-6">
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4" role="alert">
                    <p>{{ session('success') }}</p>
                </div>
            </div>
        @endif
        @if (session('error'))
            <div class="container mx-auto px-4 mb-6">
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                    <p>{{ session('error') }}</p>
                </div>
            </div>
        @endif
        
        {{-- Konten dari halaman lain akan dimasukkan di sini --}}
        @yield('content')
    </main>
</body>
</html>
