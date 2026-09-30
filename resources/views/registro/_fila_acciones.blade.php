@php
    $tipoDetalle = $tipoDetalle ?? 'movimiento';
    $tipoEditar = $tipoEditar ?? null;
    $editLabel = $editLabel ?? 'Editar';
    $confirmTitle = $confirmTitle ?? 'Anular';
    $confirmOk = $confirmOk ?? 'Sí, anular';
@endphp
<td class="proveedor-grid__actions">
    <div class="proveedor-grid__action-group">
        <button
            type="button"
            class="icon-action icon-action--view"
            data-registro-detalle="{{ $tipoDetalle }}"
            data-registro-detalle-id="{{ $id }}"
            title="Ver detalle"
            aria-label="Ver detalle"
        >
            @include('partials.icons.eye')
        </button>
        @if ($puedeEditar && $tipoEditar)
            <button
                type="button"
                class="icon-action icon-action--edit"
                data-registro-editar="{{ $tipoEditar }}"
                data-registro-editar-id="{{ $id }}"
                title="{{ $editLabel }}"
                aria-label="{{ $editLabel }}"
            >
                @include('partials.icons.edit')
            </button>
        @endif
        @if ($puedeEliminar)
            <form
                method="POST"
                action="{{ $destroyRoute }}"
                data-confirm="{{ $confirmMessage }}"
                data-confirm-title="{{ $confirmTitle }}"
                data-confirm-ok="{{ $confirmOk }}"
                data-confirm-variant="danger"
            >
                @csrf
                @method('DELETE')
                <button type="submit" class="icon-action icon-action--delete" title="Anular" aria-label="Anular">@include('partials.icons.trash')</button>
            </form>
        @endif
    </div>
</td>
