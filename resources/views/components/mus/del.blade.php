@props(['action', 'what' => 'este registro'])
<form method="POST" action="{{ $action }}" data-confirm="¿Eliminar {{ $what }}? Esta acción no se puede deshacer."
      style="display:inline">
    @csrf
    @method('DELETE')
    <button type="submit" class="mb mb--danger mb--icon" aria-label="Eliminar" title="Eliminar">
        <x-mus.icon name="trash" :w="15" stroke-width="1.9" />
    </button>
</form>
