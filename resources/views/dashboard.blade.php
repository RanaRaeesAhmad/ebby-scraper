@extends('layouts.master')

@section('title', 'Dashboard')

@section('content')
<div class="card shadow mb-5 mt-4">
    <div class="card-header bg-white py-3">
        <h4 class="mb-0"><i class="bi bi-speedometer2 me-2"></i> Dashboard</h4>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('process.excel') }}" enctype="multipart/form-data" class="needs-validation" novalidate>
            @csrf
            <div class="mb-4">
                <label for="excelFile" class="form-label">Upload Your Excel File</label>
                <input class="form-control" type="file" id="excelFile" name="excel_file" accept=".xlsx,.xls" required>
                <div class="invalid-feedback">Please select a valid Excel file.</div>
            </div>
            <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-upload me-2"></i> Process File
            </button>
        </form>
    </div>
</div>
<div class="card shadow mb-5 mt-4">
    <div class="card-header bg-white py-3">
        <h4 class="mb-0"><i class="bi bi-setting me-2"></i> Refine With Sold Items</h4>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('process.refined') }}" enctype="multipart/form-data" class="needs-validation" novalidate>
            @csrf
            <div class="mb-4">
                <label for="excelFile" class="form-label">Upload Refined Excel File</label>
                <input class="form-control" type="file" id="excelFile" name="excel_file" accept=".xlsx,.xls" required>
                <div class="invalid-feedback">Please select a valid Excel file.</div>
            </div>
            <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-funnel me-2"></i> Refine Products
            </button>
        </form>
    </div>
</div>



@endsection