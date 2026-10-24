<h3>Hello {{$username}}!</h3>

<p>
    You may use the link located in this email to reset your password for your
    account at {{config('polr.app_name')}}.
</p>

<a href='{{config('polr.app_protocol')}}{{config('polr.app_address')}}/reset_password/{{$username}}/{{$recovery_key}}'>
    {{config('polr.app_protocol')}}{{config('polr.app_address')}}/reset_password/{{$username}}/{{$recovery_key}}
</a>

<br />

<p>Thanks,</p>
<p>The {{config('polr.app_name')}} team.</p>

--
<br />
You received this email because someone with the IP {{$ip}} requested a password reset
for an account at {{config('polr.app_protocol')}}{{config('polr.app_address')}}. If this was not you,
you may ignore this email.
