@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="alert alert-success">
            {{ $message }}
        </div>
        <p>You can now receive payments through our platform.</p>
        <!-- Add any additional instructions or next steps -->
    </div>
@endsection