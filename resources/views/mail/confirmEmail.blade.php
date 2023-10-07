<h1>Hello!</h1>

<p>Thank you for registering to {{ config('app.name') }}.</p>

<p>Please confirm your email adress by clicking the following link: <a href="{{  config('app.url') }}/email/confirm/{{ $token }}">{{  config('app.url') }}/email/confirm/{{ $token }}</a></p>

<p>If you did not register an account, please ignore this message.</p>

<p>Regards,<br>{{  config('app.url') }}</p>