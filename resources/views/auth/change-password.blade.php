<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar contraseña — Opland</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center">
    <div class="w-full max-w-sm bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
        <h1 class="text-lg font-semibold text-gray-800 mb-1">Cambiar contraseña</h1>
        <p class="text-sm text-gray-400 mb-6">Es tu primer acceso. Establece una contraseña nueva para continuar.</p>

        @if($errors->any())
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-600">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('password.change.update') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Nueva contraseña</label>
                <input type="password" name="password" required minlength="8"
                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Confirmar contraseña</label>
                <input type="password" name="password_confirmation" required
                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
            </div>
            <button type="submit"
                    class="w-full bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium py-2 rounded-lg transition-colors">
                Guardar contraseña
            </button>
        </form>
    </div>
</body>
</html>
