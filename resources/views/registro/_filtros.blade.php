<form

    method="GET"

    action="{{ route('registro.index') }}"

    class="registro-filters"

    data-grid-filters

    data-clear-url="{{ route('registro.index', ['tab' => $tabActiva ?? 'ingresos']) }}"

>

    @php

        $exportParams = array_filter([

            'tab' => $tabActiva ?? 'ingresos',

            'fecha' => request('fecha', $filtros['fecha'] ?? null),

            'almacen' => request('almacen', $filtros['id_almacen'] ?? null),

            'buscar' => request('buscar', $filtros['buscar'] ?? null),

        ], fn ($valor) => filled($valor));

    @endphp



    <input type="hidden" name="tab" value="{{ $tabActiva ?? 'ingresos' }}">



    <div class="registro-filters__main">

        <label class="registro-filters__field">

            <span class="registro-filters__label">Fecha</span>

            <input

                type="date"

                id="registro-filtro-fecha"

                name="fecha"

                class="registro-filters__input"

                value="{{ $filtros['fecha'] ?? request('fecha') }}"

            >

        </label>



        <label class="registro-filters__field registro-filters__field--search">

            <span class="registro-filters__label">Buscar</span>

            <input

                type="search"

                id="registro-buscar"

                name="buscar"

                class="registro-filters__input registro-filters__input--search"

                placeholder="Factura, guía o código PL"

                autocomplete="off"

                value="{{ $filtros['buscar'] ?? request('buscar') }}"

            >

        </label>



        <label class="registro-filters__field">

            <span class="registro-filters__label">Almacén</span>

            <select id="registro-filtro-almacen" name="almacen" class="registro-filters__input">

                <option value="">Todos</option>

                @foreach ($almacenes as $almacen)

                    <option value="{{ $almacen->idAlmacen }}" @selected((string) ($filtros['id_almacen'] ?? request('almacen')) === (string) $almacen->idAlmacen)>

                        {{ $almacen->codigo }}

                    </option>

                @endforeach

            </select>

        </label>



        <div class="registro-filters__clear-wrap">

            <button type="button" class="btn btn--outline btn--sm" id="registro-limpiar" data-grid-filters-clear>Limpiar</button>

        </div>

    </div>



    @include('partials.export-actions', [
        'pdfUrl' => route('registro.export.pdf', $exportParams),
        'excelUrl' => route('registro.export.excel', $exportParams),
    ])

</form>

