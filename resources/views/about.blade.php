@extends('layouts.base')

@section('css')
<link rel='stylesheet' href='/css/about.css' />
<link rel='stylesheet' href='/css/effects.css' />
@endsection

@section('content')
<div class='well logo-well'>
    <img class='logo-img' src='/img/logo.png' />
</div>

<div class='about-contents'>
    @if ($role == "admin")
    <dl>
        <p>Build Information</p>
        <dt>Version: {{config('polr.polr_version')}}</dt>
        <dt>Release date: {{config('polr.polr_reldate')}}</dt>
        <dt>App Install: {{config('polr.app_name')}} on {{config('polr.app_address')}} on {{config('polr.generated_at')}}<dt>
    </dl>
    <p>You are seeing the information above because you are logged in as an administrator. You can edit the contents of this page by editing <code>resources/views/about.blade.php</code></p>
    @endif

    <p>{{config('polr.app_name')}} is powered by Polr 2, an open source, minimalist link shortening platform. The Polr Project is in no way associated with this site.
        Learn more at <a href='https://github.com/Cydrobolt/polr'>its Github page</a> or its <a href="//project.polr.me">project site</a>.
        <br />Polr is licensed under the GNU GPL License.
    </p>
    @if (config('polr.setting_adv_analytics'))
    <p>This product includes GeoLite2 data created by MaxMind, available from <a href="https://www.maxmind.com">https://www.maxmind.com</a>.</p>
    @endif
</div>

@endsection
