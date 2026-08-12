@php($unit = $unit ?? null)

<div class="mb-4">
    <label class="block mb-1 font-medium">Nombre</label>
    <input type="text" name="name" value="{{ old('name', $unit->name ?? '') }}"
           class="w-full border rounded px-3 py-2">
    @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
</div>

<div class="mb-4">
    <label class="block mb-1 font-medium">Símbolo</label>
    <input type="text" name="symbol" value="{{ old('symbol', $unit->symbol ?? '') }}"
           class="w-full border rounded px-3 py-2">
    @error('symbol') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
</div>

<div class="mb-4">
    <label class="block mb-1 font-medium">Tipo (opcional)</label>
    <input type="text" name="type" value="{{ old('type', $unit->type ?? '') }}"
           class="w-full border rounded px-3 py-2">
    @error('type') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
</div>
