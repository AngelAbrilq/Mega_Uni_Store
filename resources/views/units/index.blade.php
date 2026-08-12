<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Unidades de medida</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">

                @if (session('success'))
                    <div class="mb-4 rounded bg-green-100 text-green-800 px-4 py-2">
                        {{ session('success') }}
                    </div>
                @endif

                <a href="{{ route('units.create') }}"
                    class="inline-block mb-4 px-4 py-2 bg-gray-800 text-white rounded">
                    + Nueva unidad
                </a>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b">
                                <th class="py-2">ID</th>
                                <th class="py-2">Nombre</th>
                                <th class="py-2">Símbolo</th>
                                <th class="py-2">Tipo</th>
                                <th class="py-2 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($units as $unit)
                                <tr class="border-b">
                                    <td class="py-2">{{ $unit->id }}</td>
                                    <td class="py-2">{{ $unit->name }}</td>
                                    <td class="py-2">{{ $unit->symbol }}</td>
                                    <td class="py-2">{{ $unit->type ?? '—' }}</td>
                                    <td class="py-2 text-right">
                                        <a href="{{ route('units.edit', $unit) }}" class="text-blue-600">Editar</a>
                                        <form action="{{ route('units.destroy', $unit) }}" method="POST" class="inline"
                                            onsubmit="return confirm('¿Eliminar esta unidad?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-red-600 ml-2">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-4 text-gray-500">No hay unidades todavía.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    {{ $units->links() }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>