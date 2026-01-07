<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>QxLog - Control Quirúrgico</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full font-sans antialiased text-slate-600">
        <div class="relative min-h-screen flex flex-col">
            <!-- Navbar -->
            <nav class="relative z-10 px-6 py-4 flex justify-between items-center max-w-7xl mx-auto w-full">
                <div class="flex items-center gap-2">
                    <div class="bg-teal-600 p-2 rounded-lg">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-white">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                    </div>
                    <span class="text-xl font-bold text-slate-800 tracking-tight">QxLog</span>
                </div>
                
                <div class="flex items-center gap-4">
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/dashboard') }}" class="text-sm font-medium text-slate-700 hover:text-teal-600 transition">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="text-sm font-medium text-slate-700 hover:text-teal-600 transition">
                                Iniciar Sesión
                            </a>
                        @endauth
                    @endif
                </div>
            </nav>

            <!-- Hero Section -->
            <main class="flex-grow flex items-center justify-center relative overflow-hidden">
                <!-- Background decorative blobs -->
                <div class="absolute top-0 left-0 w-full h-full overflow-hidden -z-10">
                    <div class="absolute -top-[10%] -left-[10%] w-[50%] h-[50%] rounded-full bg-teal-100 blur-3xl opacity-60 mix-blend-multiply"></div>
                    <div class="absolute top-[20%] -right-[10%] w-[40%] h-[40%] rounded-full bg-blue-100 blur-3xl opacity-60 mix-blend-multiply"></div>
                </div>

                <div class="max-w-7xl mx-auto px-6 py-12 lg:py-24 grid lg:grid-cols-2 gap-12 items-center">
                    <div class="text-center lg:text-left space-y-8">
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-teal-50 border border-teal-100 text-teal-700 text-xs font-semibold uppercase tracking-wide">
                            <span class="w-2 h-2 rounded-full bg-teal-500 animate-pulse"></span>
                            Sistema Hospitalario
                        </div>
                        <h1 class="text-4xl lg:text-5xl font-bold text-slate-900 leading-tight">
                            Control Quirúrgico <br>
                            <span class="text-teal-600">Automatizado y Preciso</span>
                        </h1>
                        <p class="text-lg text-slate-600 max-w-2xl mx-auto lg:mx-0">
                            Olvídate del papel. Gestiona procedimientos, automatiza pagos a instrumentistas y genera vouchers con validez administrativa en segundos.
                        </p>
                        
                        <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
                            @if (Route::has('login'))
                                <a href="{{ route('login') }}" class="inline-flex justify-center items-center px-6 py-3 text-base font-medium text-white bg-teal-600 hover:bg-teal-700 rounded-lg shadow-lg shadow-teal-600/20 transition-all transform hover:-translate-y-0.5">
                                    Ingresar al Sistema
                                </a>
                            @endif
                        </div>

                        <!-- Stats / Features Mini Grid -->
                        <div class="pt-8 border-t border-slate-200 grid grid-cols-3 gap-4 text-center lg:text-left">
                            <div>
                                <p class="text-2xl font-bold text-slate-800">100%</p>
                                <p class="text-sm text-slate-500">Digital</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-slate-800">24/7</p>
                                <p class="text-sm text-slate-500">Acceso</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-slate-800">Auto</p>
                                <p class="text-sm text-slate-500">Cálculos</p>
                            </div>
                        </div>
                    </div>

                    <!-- Illustration / Abstract Representation -->
                    <div class="relative hidden lg:block">
                        <div class="relative bg-white p-8 rounded-2xl shadow-xl shadow-slate-200/50 border border-slate-100 transform rotate-2 hover:rotate-0 transition-transform duration-500">
                            <div class="flex items-center gap-4 mb-6">
                                <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-xl">👩‍⚕️</div>
                                <div>
                                    <h3 class="font-bold text-slate-800">Procedimiento #4829</h3>
                                    <p class="text-sm text-slate-500">Videocirugía • 2h 15m</p>
                                </div>
                                <div class="ml-auto text-teal-600 font-bold">+ Q300.00</div>
                            </div>
                            
                            <div class="bg-slate-50 rounded-lg p-4 mb-4 space-y-3">
                                <div class="h-2 w-3/4 bg-slate-200 rounded"></div>
                                <div class="h-2 w-1/2 bg-slate-200 rounded"></div>
                            </div>

                            <div class="flex justify-between items-center text-sm">
                                <span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded">Pending</span>
                                <span class="text-slate-400">Hace 10 min</span>
                            </div>
                        </div>

                         <div class="absolute -bottom-6 -right-6 -z-10 bg-teal-600 p-6 rounded-2xl shadow-lg w-full h-full opacity-10"></div>
                    </div>
                </div>
            </main>

            <footer class="py-6 text-center text-sm text-slate-500">
                &copy; {{ date('Y') }} QxLog. Sistema de Gestión Hospitalaria.
            </footer>
        </div>
    </body>
</html>
