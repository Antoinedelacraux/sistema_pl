<section class="form-section form-section--permisos" id="permisos-section">
    <div class="form-section__head">
        <h3 class="form-section__title">Módulos y privilegios</h3>
        <button type="button" class="btn btn--outline btn--sm" id="permisos-marcar-todos">Marcar todos</button>
    </div>

    <p class="form-section__hint">Define qué módulos ve el usuario y qué puede hacer en cada uno.</p>

    <div class="perm-table-wrap">
        <table class="perm-table">
            <thead>
                <tr>
                    <th>Módulo</th>
                    @foreach ($permisosAcciones as $key => $label)
                        <th>{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($modulos as $modulo)
                    @php
                        $fila = $permisosMatriz[$modulo->codigo] ?? [];
                    @endphp
                    <tr>
                        <th scope="row">{{ $modulo->nombre }}</th>
                        @foreach ($permisosAcciones as $accion => $label)
                            @php
                                $checked = ! empty($fila[$accion]);
                            @endphp
                            <td>
                                <label class="perm-check">
                                    <input
                                        type="checkbox"
                                        name="permisos[{{ $modulo->codigo }}][{{ $accion }}]"
                                        value="1"
                                        class="perm-check__input"
                                        data-modulo="{{ $modulo->codigo }}"
                                        data-accion="{{ $accion }}"
                                        @checked($checked)
                                    >
                                    <span class="perm-check__box" aria-hidden="true"></span>
                                </label>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<script type="application/json" id="permisos-por-rol">{!! $permisosPorRolJson !!}</script>

@push('scripts')
<script src="{{ asset('js/usuario-permisos.js') }}" defer></script>
@endpush
