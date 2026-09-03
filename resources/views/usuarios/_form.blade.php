@php
    $persona = $cuenta->persona ?? null;
    $values = [
        'nombres' => old('nombres', $persona?->Nombres),
        'apellidos' => old('apellidos', $persona?->Apellidos),
        'correo' => old('correo', $persona?->Correo),
        'id_area' => old('id_area', $persona?->idArea),
        'id_cargo' => old('id_cargo', $persona?->idCargo),
        'id_rol' => old('id_rol', $cuenta?->idRol),
        'usuario' => old('usuario', $cuenta?->Usuario),
        'estado' => old('estado', $cuenta?->Estado ?? \App\Models\Estado::ACTIVO),
    ];
    $editando = isset($cuenta) && $cuenta->exists;
@endphp

<div class="form-card">
    @unless($editando)
        <header class="form-card__intro">
            <p class="form-card__eyebrow">Nueva cuenta</p>
            <p class="form-card__lead">Registra la persona y sus datos de acceso al sistema.</p>
        </header>
    @endunless

    <section class="form-section">
        <h3 class="form-section__title">Datos personales</h3>
        <div class="form-grid">
            <label class="field">
                <span class="field__label">Nombres</span>
                <input class="field__input @error('nombres') is-invalid @enderror" type="text" name="nombres" value="{{ $values['nombres'] }}" required>
            </label>

            <label class="field">
                <span class="field__label">Apellidos</span>
                <input class="field__input @error('apellidos') is-invalid @enderror" type="text" name="apellidos" value="{{ $values['apellidos'] }}" required>
            </label>

            <label class="field field--full">
                <span class="field__label">Correo</span>
                <input class="field__input @error('correo') is-invalid @enderror" type="email" name="correo" value="{{ $values['correo'] }}" required>
            </label>
        </div>
    </section>

    <section class="form-section form-section--org">
        <h3 class="form-section__title">Organización</h3>
        <div class="form-grid">
            <label class="field">
                <span class="field__label">Área</span>
                <select class="field__input @error('id_area') is-invalid @enderror" name="id_area" required>
                    <option value="">Seleccionar</option>
                    @foreach ($areas as $area)
                        <option value="{{ $area->idArea }}" @selected((int) $values['id_area'] === (int) $area->idArea)>
                            {{ $area->nomArea }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="field">
                <span class="field__label">Cargo</span>
                <select class="field__input @error('id_cargo') is-invalid @enderror" name="id_cargo" required>
                    <option value="">Seleccionar</option>
                    @foreach ($cargos as $cargo)
                        <option value="{{ $cargo->idCargo }}" @selected((int) $values['id_cargo'] === (int) $cargo->idCargo)>
                            {{ $cargo->nomCargo }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="field">
                <span class="field__label">Rol</span>
                <select class="field__input @error('id_rol') is-invalid @enderror" name="id_rol" id="id_rol" required>
                    <option value="">Seleccionar</option>
                    @foreach ($roles as $rol)
                        <option value="{{ $rol->idRol }}" @selected((int) $values['id_rol'] === (int) $rol->idRol)>
                            {{ $rol->Nombre }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="field">
                <span class="field__label">Estado</span>
                <select class="field__input @error('estado') is-invalid @enderror" name="estado" required>
                    <option value="1" @selected((int) $values['estado'] === 1)>Activo</option>
                    <option value="0" @selected((int) $values['estado'] === 0)>Inactivo</option>
                </select>
            </label>
        </div>
    </section>

    @include('usuarios._permisos')

    <section class="form-section">
        <h3 class="form-section__title">Acceso</h3>
        <div class="form-grid">
            <label class="field">
                <span class="field__label">Usuario de acceso</span>
                <input class="field__input @error('usuario') is-invalid @enderror" type="text" name="usuario" value="{{ $values['usuario'] }}" required>
            </label>

            <label class="field">
                <span class="field__label">
                    Contraseña
                    @if ($editando)
                        <span class="field__hint">Dejar en blanco para no cambiar</span>
                    @endif
                </span>
                <input
                    class="field__input @error('password') is-invalid @enderror"
                    type="password"
                    name="password"
                    @unless($editando) required @endunless
                    autocomplete="new-password"
                >
            </label>
        </div>
    </section>

    <div class="form-actions">
        <a href="{{ route('usuarios.index') }}" class="btn btn--outline">Cancelar</a>
        <button type="submit" class="btn btn--purple">{{ $editando ? 'Guardar cambios' : 'Crear usuario' }}</button>
    </div>
</div>
