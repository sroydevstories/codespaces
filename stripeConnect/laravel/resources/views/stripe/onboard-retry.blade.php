@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="alert alert-warning">
            {{ $message }}
        </div>
        <a href="{{ route('stripe.connect') }}" class="btn btn-primary">
            Try Connecting Again
        </a>
    </div>
@endsection