@extends('layouts.base')

@section('css')
<link rel='stylesheet' href='/css/signup.css' />
@endsection

@section('content')
<div class='col-md-6'>
    <h2 class='title'>Register</h2>

    <form action='/signup' method='POST'>
        Username: <input type='text' name='username' class='form-control form-field' placeholder='Username' />
        Password: <input type='password' name='password' class='form-control form-field' placeholder='Password' />
        Email: <input type='email' name='email' class='form-control form-field' placeholder='Email' />

        @if (config('polr.polr_acct_creation_recaptcha'))
        <div class="g-recaptcha" data-sitekey="{{config('polr.polr_recaptcha_site_key')}}"></div>
        @endif

        <input type="hidden" name='_token' value='{{csrf_token()}}' />
        <input type="submit" class="btn btn-default btn-success" value="Register"/>
        <p class='login-prompt'>
            <small>Already have an account? <a href='{{route('login')}}'>Login</a></small>
        </p>
    </form>
</div>
<div class='col-md-6 hidden-xs hidden-sm'>
    <div class='right-col-one'>
        <h4>Username</h4>
        <p>The username you will use to login to {{config('polr.app_name')}}.</p>
    </p>
    <div class='right-col-next'>
        <div class='right-col'>
            <h4>Password</h4>
            <p>The secure password you will use to login to {{config('polr.app_name')}}.</p>
        </p>
    </div>
    <div class='right-col-next'>
        <h4>Email</h4>
        <p>The email you will use to verify your account or to recover your account.</p>
    </p>
</div>
@endsection

@section('js')
    @if (config('polr.polr_acct_creation_recaptcha'))
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endif
@endsection
