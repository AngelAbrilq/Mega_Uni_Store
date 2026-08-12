<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Unidad: {{ $unit->name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <p><span class="font-medium">Nombre:</span> {{ $unit->name }}</p>
                <p><span class="font-medium">Símbolo:</span> {{ $unit->symbol }}</p>
                <p><span class="font-medium">Tipo:</span> {{ $unit->type ?? '—' }}</p>

                <a href="{{ route('units.index') }}" class="inline-block mt-4 text-gray-600">← Volver</a>
            </div>
        </div>
    </div>
</x-app-layout>
