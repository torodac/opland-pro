<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceder — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-sm">

        {{-- Logo / nombre --}}
        <div class="text-center mb-8">
            <div class="w-12 h-12 rounded-xl bg-orange-500 text-white flex items-center justify-center font-bold text-xl mx-auto mb-3">
                {{ strtoupper(substr(config('app.name'), 0, 1)) }}
            </div>
            <h1 class="text-xl font-semibold text-gray-800">{{ config('app.name') }}</h1>
            <p class="text-sm text-gray-400 mt-1">Introduce tus credenciales para acceder</p>
        </div>

        {{-- Formulario --}}
        <form method="POST" action="{{ route('login') }}" class="bg-white rounded-xl border border-gray-200 p-6 space-y-4">
            @csrf

            {{-- Email --}}
            <div>
                <label for="email" class="block text-xs font-bold text-gray-600 mb-1.5">Email</label>
                <input type="email" id="email" name="email"
                       value="{{ old('email') }}"
                       autofocus autocomplete="email"
                       class="w-full text-sm border rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300
                              {{ $errors->has('email') ? 'border-red-300' : 'border-gray-200' }}">
                @error('email')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Password --}}
            <div>
                <label for="password" class="block text-xs font-bold text-gray-600 mb-1.5">Contraseña</label>
                <input type="password" id="password" name="password"
                       autocomplete="current-password"
                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
                @error('password')
                    <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Recuérdame --}}
            <div class="flex items-center gap-2">
                <input type="checkbox" id="remember" name="remember"
                       class="w-4 h-4 accent-orange-500">
                <label for="remember" class="text-sm text-gray-500 cursor-pointer">Recordarme</label>
            </div>

            <button type="submit"
                    class="w-full py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
                Acceder
            </button>

            <div class="text-center">
                <a href="{{ route('password.request') }}" class="text-xs text-gray-400 hover:text-orange-500 transition-colors">
                    ¿Olvidaste tu contraseña?
                </a>
            </div>
        </form>

    </div>

</body>
</html>
