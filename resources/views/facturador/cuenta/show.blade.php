@extends('facturador.layouts.app')

@section('title', 'Mi cuenta')

@section('content')
    <div class="mb-4">
        <h1 class="fac-page-title">Mi cuenta</h1>
        <p class="fac-page-subtitle">Tu información de acceso y empresa.</p>
    </div>
    <div class="row g-4">
        <div class="col-lg-6">
            <section class="fac-card fac-card-pad h-100">
                <h2 class="fac-section-title mb-4">Perfil</h2>
                <div class="mb-3">
                    <div class="small text-secondary">Nombre</div>
                    <div class="fw-semibold mt-1">{{ auth()->user()->name }}</div>
                </div>
                <div>
                    <div class="small text-secondary">Correo</div>
                    <div class="fw-semibold mt-1">{{ auth()->user()->email }}</div>
                </div>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="fac-card fac-card-pad h-100">
                <h2 class="fac-section-title mb-4">Empresa</h2>
                <div class="mb-3">
                    <div class="small text-secondary">Razón social</div>
                    <div class="fw-semibold mt-1">{{ $empresa->razon_social }}</div>
                </div>
                <div>
                    <div class="small text-secondary">RUC</div>
                    <div class="fw-semibold mt-1">{{ $empresa->ruc }}</div>
                </div>
            </section>
        </div>
    </div>
@endsection
