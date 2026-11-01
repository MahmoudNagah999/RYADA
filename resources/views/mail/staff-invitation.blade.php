<p>{{ __('mail.staff_invitation.greeting') }}</p>
<p>{{ __('mail.staff_invitation.invited', ['club' => $clubName, 'role' => $roleLabel]) }}</p>
<p>{{ __('mail.staff_invitation.action') }}</p>
<p><a href="{{ $acceptUrl }}">{{ $acceptUrl }}</a></p>
<p>{{ __('mail.staff_invitation.expires', ['date' => $expiresAt]) }}</p>
<p>{{ __('mail.staff_invitation.ignore') }}</p>
