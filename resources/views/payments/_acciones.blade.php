{{-- Aprobar o rechazar un pago pendiente (RN-32). Rechazar exige motivo. --}}
@can('verificar', $pago)
    <div class="pacc">
        <form method="POST" action="{{ route('payments.aprobar', $pago) }}">
            @csrf
            <button type="submit" class="mb mb--primary mb--sm">Llegó · aprobar</button>
        </form>
        <form method="POST" action="{{ route('payments.rechazar', $pago) }}">
            @csrf
            <input type="text" name="rejection_reason" required minlength="3" maxlength="255"
                   placeholder="Motivo del rechazo" aria-label="Motivo del rechazo">
            <button type="submit" class="mb mb--ghost mb--sm">Rechazar</button>
        </form>
    </div>
@endcan
