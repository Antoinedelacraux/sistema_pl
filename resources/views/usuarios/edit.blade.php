@extends('layouts.shell')

@section('title', 'Editar usuario')

@section('heading', 'Editar usuario')
@section('crumbs', $cuenta->persona?->nombreCompleto() ?? $cuenta->Usuario)

@section('page')
@include('partials.flash')

<div class="form-page">
    <form method="POST" action="{{ route('usuarios.update', $cuenta) }}" class="usuario-form">
        @csrf
        @method('PUT')
        @include('usuarios._form')
    </form>
</div>
@endsection
