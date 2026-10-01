{{-- Plain-text email: echo with {!! !!}; {{ }} would HTML-escape the link's "&" and break its signature. --}}
Hi {!! $user->name !!},

@if($user->isPendingInvite())
You now have an account on the Lawa't Kape system.

Your username: {!! $user->username ?? $user->email !!}

Open this link to choose your password. You'll be signed in right after:
@else
Someone at Lawa't Kape asked for a new password for your account ({!! $user->username ?? $user->email !!}).

Open this link to choose a new one:
@endif

{!! $link !!}

The link works once and for {!! $days !!} days. Open it while connected to the shop Wi-Fi.
If it has expired, ask the owner to send a new one.

— Lawa't Kape
