@extends('layouts.shell')

@section('title', 'Nuevo usuario')

@section('heading', 'Nuevo usuario')
@section('crumbs', 'Usuarios · crear cuenta')

@section('page')
@include('partials.flash')

<div class="form-page">
    <form method="POST" action="{{ route('usuarios.store') }}" class="usuario-form">
        @csrf
        @include('usuarios._form', ['cuenta' => null])
    </form>
</div>
@endsection
