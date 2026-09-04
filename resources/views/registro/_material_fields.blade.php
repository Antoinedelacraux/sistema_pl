<label class="field">
    <span class="field__label">Código planeamiento</span>
    <select
        class="field__input @error('id_material_pl') is-invalid @enderror material-pl-select"
        @if (empty($pickerOnly)) name="id_material_pl" @endif
        data-material-select="{{ $prefix }}"
        @if (empty($pickerOnly)) required @endif
    >
        <option value="">Seleccionar material</option>
        @foreach (($materiales ?? collect()) as $item)
            <option
                value="{{ $item->idMaterialPL }}"
                data-codigo-pl="{{ $item->CodigoPL }}"
                data-nom-material-pl="{{ $item->nomMaterial }}"
                @selected((int) old('id_material_pl') === (int) $item->idMaterialPL)
            >{{ $item->CodigoPL }} — {{ Str::limit($item->nomMaterial, 60) }}</option>
        @endforeach
    </select>
</label>

<label class="field">
    <span class="field__label">Buscar por código PL</span>
    <input
        type="text"
        class="field__input material-pl-search"
        data-material-search="{{ $prefix }}"
        placeholder="Escriba el código PL"
        maxlength="20"
        autocomplete="off"
    >
</label>

<label class="field field--full">
    <span class="field__label">Material</span>
    <input
        type="text"
        class="field__input material-pl-nom"
        data-material-nom="{{ $prefix }}"
        readonly
    >
</label>
