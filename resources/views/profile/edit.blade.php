@extends('layouts.master')

@section('title', 'Profile Settings')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="card shadow mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i> Profile Information</h5>
            </div>
            <div class="card-body">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>
    </div>

    <div class="col-lg-12">
        <div class="card shadow mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-shield-lock me-2"></i> Update Password</h5>
            </div>
            <div class="card-body">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i> Delete Account</h5>
            </div>
            <div class="card-body">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</div>
@endsection