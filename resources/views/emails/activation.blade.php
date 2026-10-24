<h3>Hello {{$username}}!</h3>

<p>Thanks for registering at {{config('polr.app_name')}}. To use your account,
you will need to activate it by clicking the following link:</p>

<br />

<a href='{{config('polr.app_protocol')}}{{config('polr.app_address')}}/activate/{{$username}}/{{$recovery_key}}'>
    {{config('polr.app_protocol')}}{{config('polr.app_address')}}/activate/{{$username}}/{{$recovery_key}}
</a>

<br />

<p>Thanks,</p>
<p>The {{config('polr.app_name')}} team.</p>

--
<br />
You received this email because someone with the IP {{$ip}} signed up
for an account at {{config('polr.app_protocol')}}{{config('polr.app_address')}}. If this was not you,
you may ignore this email.
